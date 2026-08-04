<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AdminController extends Controller
{
    private function authorizeAdmin(Request $request): void
    {
        if (! $request->user()->hasRole('admin')) {
            abort(403);
        }
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);

        $users = User::with('roles')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        $users->getCollection()->transform(fn (User $u) => $this->format($u));

        return response()->json($users);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', Password::defaults()],
            'role' => ['required', 'string', 'exists:roles,name'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        $user->syncRoles([$validated['role']]);

        AuditLog::record('admin.user.created', [
            'subject_id' => $user->id,
            'subject_email' => $user->email,
            'role' => $validated['role'],
        ], $request);

        return response()->json($this->format($user->load('roles')), 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $this->authorizeAdmin($request);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255', 'unique:users,email,'.$user->id],
            'password' => ['sometimes', 'nullable', Password::defaults()],
            'role' => ['sometimes', 'string', 'exists:roles,name'],
        ]);

        if (isset($validated['password']) && $validated['password']) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        if (isset($validated['role'])) {
            // A role change is the single most privilege-relevant write in the
            // application, so the previous role is recorded too.
            AuditLog::record('admin.user.role_changed', [
                'subject_id' => $user->id,
                'subject_email' => $user->email,
                'from' => $user->roles->pluck('name')->all(),
                'to' => $validated['role'],
            ], $request);

            $user->syncRoles([$validated['role']]);
            unset($validated['role']);
        }

        if (isset($validated['password'])) {
            AuditLog::record('admin.user.password_reset', [
                'subject_id' => $user->id,
                'subject_email' => $user->email,
            ], $request);
        }

        $user->update($validated);

        return response()->json($this->format($user->load('roles')));
    }

    public function destroy(Request $request, User $user): Response
    {
        $this->authorizeAdmin($request);

        if ($request->user()->id === $user->id) {
            abort(422, 'You cannot delete your own account.');
        }

        AuditLog::record('admin.user.deleted', [
            'subject_id' => $user->id,
            'subject_email' => $user->email,
            'roles' => $user->roles->pluck('name')->all(),
        ], $request);

        $user->delete();

        return response()->noContent();
    }

    public function stats(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);

        $usersByRole = DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->groupBy('roles.name')
            ->selectRaw('roles.name as role, COUNT(*) as count')
            ->pluck('count', 'role');

        $hotelStats = DB::table('bookings')
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $ferryStats = DB::table('ferry_tickets')
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $parkStats = DB::table('event_bookings')
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        // Summed from the booking's own stored total rather than recomputed from
        // the dates: `total_price` is already nights x price_per_night (set in
        // HotelBookingService::create), so this drops a join and the MySQL-only
        // DATEDIFF that made this endpoint unrunnable - and therefore untestable
        // - on SQLite.
        $hotelRevenue = DB::table('bookings')
            ->where('status', 'confirmed')
            ->sum('total_price');

        $parkRevenue = DB::table('event_bookings')
            ->join('event_slots', 'event_slots.id', '=', 'event_bookings.event_slot_id')
            ->join('theme_park_events', 'theme_park_events.id', '=', 'event_slots.event_id')
            ->where('event_bookings.status', '!=', 'cancelled')
            ->selectRaw('SUM(event_bookings.ticket_count * theme_park_events.price_per_ticket) as total')
            ->value('total') ?? 0;

        // Thirty days of daily activity. The rest of this payload is all
        // point-in-time totals, which can say how much there is but never
        // whether it is growing - the overview had no way to plot a trend.
        $since = now()->subDays(29)->startOfDay();
        $days = collect(range(29, 0))->map(fn (int $back) => now()->subDays($back)->toDateString());

        $countByDay = fn (string $table) => DB::table($table)
            ->where('created_at', '>=', $since)
            ->selectRaw('DATE(created_at) as day, COUNT(*) as aggregate')
            ->groupBy('day')
            ->pluck('aggregate', 'day');

        $hotelDaily = $countByDay('bookings');
        $ferryDaily = $countByDay('ferry_tickets');
        $parkDaily = $countByDay('event_bookings');

        $hotelRevenueDaily = DB::table('bookings')
            ->where('status', 'confirmed')
            ->where('created_at', '>=', $since)
            ->selectRaw('DATE(created_at) as day, SUM(total_price) as aggregate')
            ->groupBy('day')
            ->pluck('aggregate', 'day');

        $parkRevenueDaily = DB::table('event_bookings')
            ->join('event_slots', 'event_slots.id', '=', 'event_bookings.event_slot_id')
            ->join('theme_park_events', 'theme_park_events.id', '=', 'event_slots.event_id')
            ->where('event_bookings.status', '!=', 'cancelled')
            ->where('event_bookings.created_at', '>=', $since)
            ->selectRaw('DATE(event_bookings.created_at) as day, SUM(event_bookings.ticket_count * theme_park_events.price_per_ticket) as aggregate')
            ->groupBy('day')
            ->pluck('aggregate', 'day');

        // Every day in the window is emitted, including the empty ones - a
        // series built only from days that have rows draws a flat line through
        // the gaps and overstates a quiet week.
        $daily = $days->map(fn (string $day) => [
            'date' => $day,
            'hotel' => (int) ($hotelDaily[$day] ?? 0),
            'ferry' => (int) ($ferryDaily[$day] ?? 0),
            'park' => (int) ($parkDaily[$day] ?? 0),
            'revenue' => round((float) ($hotelRevenueDaily[$day] ?? 0) + (float) ($parkRevenueDaily[$day] ?? 0), 2),
        ]);

        return response()->json([
            'daily' => $daily,
            'users' => [
                'total' => User::count(),
                'by_role' => $usersByRole,
                'guests' => User::where('is_guest', true)->count(),
            ],
            'hotel_bookings' => [
                'total' => array_sum($hotelStats->toArray()),
                'confirmed' => $hotelStats['confirmed'] ?? 0,
                'pending' => $hotelStats['pending'] ?? 0,
                'cancelled' => $hotelStats['cancelled'] ?? 0,
                'revenue' => number_format($hotelRevenue, 2, '.', ''),
            ],
            'ferry_tickets' => [
                'total' => array_sum($ferryStats->toArray()),
                'issued' => $ferryStats['issued'] ?? 0,
                'used' => $ferryStats['used'] ?? 0,
                'cancelled' => $ferryStats['cancelled'] ?? 0,
            ],
            'park_bookings' => [
                'total' => array_sum($parkStats->toArray()),
                'confirmed' => $parkStats['confirmed'] ?? 0,
                'used' => $parkStats['used'] ?? 0,
                'cancelled' => $parkStats['cancelled'] ?? 0,
                'revenue' => number_format($parkRevenue, 2, '.', ''),
            ],
        ]);
    }

    private function format(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'is_guest' => $user->is_guest,
            'role' => $user->roles->first()?->name,
            'created_at' => $user->created_at?->toDateString(),
        ];
    }
}

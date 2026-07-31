<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
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
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', Password::defaults()],
            'role'     => ['required', 'string', 'exists:roles,name'],
        ]);

        $user = User::create([
            'name'     => $validated['name'],
            'email'    => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        $user->syncRoles([$validated['role']]);

        return response()->json($this->format($user->load('roles')), 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $this->authorizeAdmin($request);

        $validated = $request->validate([
            'name'     => ['sometimes', 'string', 'max:255'],
            'email'    => ['sometimes', 'email', 'max:255', 'unique:users,email,'.$user->id],
            'password' => ['sometimes', 'nullable', Password::defaults()],
            'role'     => ['sometimes', 'string', 'exists:roles,name'],
        ]);

        if (isset($validated['password']) && $validated['password']) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        if (isset($validated['role'])) {
            $user->syncRoles([$validated['role']]);
            unset($validated['role']);
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
            ->selectRaw("status, COUNT(*) as count")
            ->groupBy('status')
            ->pluck('count', 'status');

        $ferryStats = DB::table('ferry_tickets')
            ->selectRaw("status, COUNT(*) as count")
            ->groupBy('status')
            ->pluck('count', 'status');

        $parkStats = DB::table('event_bookings')
            ->selectRaw("status, COUNT(*) as count")
            ->groupBy('status')
            ->pluck('count', 'status');

        $hotelRevenue = DB::table('bookings')
            ->join('rooms', 'rooms.id', '=', 'bookings.room_id')
            ->where('bookings.status', 'confirmed')
            ->selectRaw('SUM(rooms.price_per_night * DATEDIFF(bookings.check_out_date, bookings.check_in_date)) as total')
            ->value('total') ?? 0;

        $parkRevenue = DB::table('event_bookings')
            ->join('event_slots', 'event_slots.id', '=', 'event_bookings.event_slot_id')
            ->join('theme_park_events', 'theme_park_events.id', '=', 'event_slots.event_id')
            ->where('event_bookings.status', '!=', 'cancelled')
            ->selectRaw('SUM(event_bookings.ticket_count * theme_park_events.price_per_ticket) as total')
            ->value('total') ?? 0;

        return response()->json([
            'users' => [
                'total'    => User::count(),
                'by_role'  => $usersByRole,
                'guests'   => User::where('is_guest', true)->count(),
            ],
            'hotel_bookings' => [
                'total'     => array_sum($hotelStats->toArray()),
                'confirmed' => $hotelStats['confirmed'] ?? 0,
                'pending'   => $hotelStats['pending'] ?? 0,
                'cancelled' => $hotelStats['cancelled'] ?? 0,
                'revenue'   => number_format($hotelRevenue, 2, '.', ''),
            ],
            'ferry_tickets' => [
                'total'     => array_sum($ferryStats->toArray()),
                'issued'    => $ferryStats['issued'] ?? 0,
                'used'      => $ferryStats['used'] ?? 0,
                'cancelled' => $ferryStats['cancelled'] ?? 0,
            ],
            'park_bookings' => [
                'total'     => array_sum($parkStats->toArray()),
                'confirmed' => $parkStats['confirmed'] ?? 0,
                'used'      => $parkStats['used'] ?? 0,
                'cancelled' => $parkStats['cancelled'] ?? 0,
                'revenue'   => number_format($parkRevenue, 2, '.', ''),
            ],
        ]);
    }

    private function format(User $user): array
    {
        return [
            'id'         => $user->id,
            'name'       => $user->name,
            'email'      => $user->email,
            'is_guest'   => $user->is_guest,
            'role'       => $user->roles->first()?->name,
            'created_at' => $user->created_at?->toDateString(),
        ];
    }
}

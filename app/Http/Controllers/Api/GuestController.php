<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\EventBooking;
use App\Models\FerryTicket;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class GuestController extends Controller
{
    /**
     * Populate a guest checkout account with real details, turning it into
     * a normal registered user without disrupting their existing session.
     */
    public function claim(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->is_guest) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user->fill([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);
        // Not fillable - see the note on the User model.
        $user->is_guest = false;
        $user->save();

        // The account has just gained a real, reachable address and a password the
        // visitor chose, so this is the point at which verification means anything.
        event(new Registered($user));

        // The privilege level of this session just changed: it went from a
        // throwaway placeholder to a real account with a known password. Rotate
        // the id so a session fixed before the claim is not still valid after it.
        $request->session()->regenerate();

        return response()->json(['user' => $user->fresh()->load('roles')]);
    }

    /**
     * Log a guest checkout session into an existing account instead, moving
     * whatever they just booked over to it and discarding the placeholder.
     */
    public function login(Request $request): JsonResponse
    {
        $guest = $request->user();

        if (! $guest->is_guest) {
            abort(403);
        }

        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        // This endpoint verifies credentials, so it needs the same brute-force
        // protection as /login. Without it this was a second, unlimited credential
        // path that reached any account in the system, admins included: the
        // per-email+IP limiter on LoginRequest did nothing here.
        $throttleKey = 'guest-login:'.Str::transliterate(
            Str::lower($validated['email']).'|'.$request->ip()
        );

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            throw ValidationException::withMessages([
                'email' => trans('auth.throttle', [
                    'seconds' => $seconds,
                    'minutes' => ceil($seconds / 60),
                ]),
            ]);
        }

        $target = User::where('email', $validated['email'])->first();

        if (! $target || ! Hash::check($validated['password'], $target->password)) {
            RateLimiter::hit($throttleKey, 60);

            throw ValidationException::withMessages([
                'email' => 'These credentials do not match our records.',
            ]);
        }

        // A guest cart is only ever merged into a visitor account. Staff sign in
        // through /login instead, which is separately throttled - allowing a
        // privileged account here turned a shopping convenience into a full
        // privilege-escalation path.
        if (! $target->hasRole('visitor')) {
            abort(403, 'This account cannot be used for guest checkout. Please sign in normally.');
        }

        RateLimiter::clear($throttleKey);

        DB::transaction(function () use ($guest, $target) {
            Booking::where('user_id', $guest->id)->update(['user_id' => $target->id]);
            EventBooking::where('user_id', $guest->id)->update(['user_id' => $target->id]);
            FerryTicket::where('user_id', $guest->id)->update(['user_id' => $target->id]);

            $guest->delete();

            Auth::login($target);
        });

        $request->session()->regenerate();

        return response()->json(['user' => $target->fresh()->load('roles')]);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\EventBooking;
use App\Models\FerryTicket;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
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

        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'is_guest' => false,
        ]);

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

        $target = User::where('email', $validated['email'])->first();

        if (! $target || ! Hash::check($validated['password'], $target->password)) {
            throw ValidationException::withMessages([
                'email' => 'These credentials do not match our records.',
            ]);
        }

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

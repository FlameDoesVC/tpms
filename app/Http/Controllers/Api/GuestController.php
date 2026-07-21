<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

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
}

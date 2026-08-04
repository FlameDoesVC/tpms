<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;

class PasswordResetLinkController extends Controller
{
    /**
     * Handle an incoming password reset link request.
     *
     * Always responds identically, whether or not the address is registered.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        // The broker's status is deliberately discarded. Surfacing it returned a
        // 422 "We can't find a user with that email address" for unknown
        // addresses, which let anyone test whether a given person holds an
        // account - useful reconnaissance before a credential-stuffing run.
        Password::sendResetLink($request->only('email'));

        return response()->json([
            'status' => __('If that email address is registered, a reset link is on its way.'),
        ]);
    }
}

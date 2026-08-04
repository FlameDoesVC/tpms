<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class GuestSession
{
    /**
     * Return the caller, provisioning and logging in a placeholder guest account
     * first if they are anonymous.
     *
     * Call this AFTER the request body has validated. This used to be route
     * middleware, which ran before validation and therefore wrote a permanent
     * `users` row (plus a role row and a session row) for any malformed request -
     * an unauthenticated, unthrottled write to the users table.
     */
    public static function ensure(Request $request): User
    {
        if (! Auth::check()) {
            Auth::login(User::createGuest());
            $request->session()->regenerate();
        }

        return $request->user();
    }
}

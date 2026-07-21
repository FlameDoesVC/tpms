<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AutoLoginGuest
{
    /**
     * Provisions and logs in a placeholder guest account for unauthenticated
     * checkout, so booking-creation endpoints always have a real user to attach to.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            Auth::login(User::createGuest());
            $request->session()->regenerate();
        }

        return $next($request);
    }
}

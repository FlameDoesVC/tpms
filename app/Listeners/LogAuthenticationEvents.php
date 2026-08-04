<?php

namespace App\Listeners;

use App\Support\AuditLog;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\PasswordReset;

/**
 * Authentication outcomes worth keeping.
 *
 * Failed sign-ins were not recorded anywhere, which is why an endpoint that
 * accepted unlimited password guesses against every account in the system
 * (audit F-01) could have been exercised indefinitely without anyone noticing.
 */
class LogAuthenticationEvents
{
    public function handleFailed(Failed $event): void
    {
        AuditLog::record('auth.login.failed', [
            // The attempted address, not the password. Never the password.
            'email' => $event->credentials['email'] ?? null,
            'known_account' => $event->user !== null,
        ]);
    }

    public function handleLockout(Lockout $event): void
    {
        AuditLog::record('auth.lockout', [
            'email' => $event->request->input('email'),
        ]);
    }

    public function handlePasswordReset(PasswordReset $event): void
    {
        AuditLog::record('auth.password.reset', [
            'subject_id' => $event->user->id,
        ]);
    }
}

<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Structured records of privileged actions.
 *
 * The application logged nothing: no record of who validated a ticket, cancelled
 * a booking, changed a user's role or reshaped a ferry deck. Every exploit found
 * in the security audit would have run without leaving a trace.
 */
class AuditLog
{
    /**
     * @param  array<string, mixed>  $context
     */
    public static function record(string $event, array $context = [], ?Request $request = null): void
    {
        $request ??= request();

        Log::channel('audit')->info($event, [
            ...$context,
            'actor_id' => $request?->user()?->id,
            'actor_roles' => $request?->user()?->getRoleNames()->all(),
            'ip' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
        ]);
    }
}

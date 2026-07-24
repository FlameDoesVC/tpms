<?php

namespace App\Support;

use Carbon\CarbonInterface;

class ScheduleRecurrence
{
    /**
     * Whether a recurrence rule (as stored on a *_templates row) produces an
     * instance on the given date. Shared by both generation and
     * reconciliation, and by both the ferry and theme-park-event templates.
     */
    public static function matches(string $frequency, ?array $weekdays, ?int $dayOfMonth, CarbonInterface $date): bool
    {
        return match ($frequency) {
            'daily' => true,
            'weekly' => in_array($date->dayOfWeekIso, $weekdays ?? [], true),
            'monthly' => $date->day === $dayOfMonth,
        };
    }
}

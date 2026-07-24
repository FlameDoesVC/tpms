<?php

namespace App\Services;

use App\Models\FerryScheduleTemplate;
use App\Support\ScheduleRecurrence;
use Carbon\Carbon;

class ScheduleTemplateGenerator
{
    private const WINDOW_DAYS = 60;

    /**
     * Materializes missing FerrySchedule rows for every active template, up
     * to the rolling window (or the template's own ends_on, if sooner).
     */
    public function generate(): void
    {
        $windowEnd = Carbon::today()->addDays(self::WINDOW_DAYS);

        FerryScheduleTemplate::query()->where('is_active', true)->each(function (FerryScheduleTemplate $template) use ($windowEnd) {
            $start = Carbon::today()->max($template->starts_on);
            $end = $template->ends_on ? $windowEnd->copy()->min($template->ends_on) : $windowEnd;

            for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
                if (! ScheduleRecurrence::matches($template->frequency, $template->weekdays, $template->day_of_month, $date)) {
                    continue;
                }

                $exists = $template->instances()->whereDate('departure_date', $date->toDateString())->exists();

                if (! $exists) {
                    $template->instances()->create([
                        'ferry_id' => $template->ferry_id,
                        'departure_date' => $date->toDateString(),
                        'departure_time' => $template->departure_time,
                        'arrival_time' => $template->arrival_time,
                        'available_seats' => $template->available_seats,
                        'status' => 'scheduled',
                    ]);
                }
            }
        });
    }

    /**
     * Syncs future, non-overridden instances with their template's current
     * fields. An instance whose date no longer matches the template's rule
     * gets flagged as overridden (never auto-cancelled) instead of touched;
     * an instance with active tickets is left alone rather than having its
     * live operational fields overwritten.
     */
    public function reconcile(): void
    {
        FerryScheduleTemplate::query()->where('is_active', true)->each(function (FerryScheduleTemplate $template) {
            $template->instances()
                ->where('is_overridden', false)
                ->whereDate('departure_date', '>=', Carbon::today())
                ->each(function ($instance) use ($template) {
                    $ruleMatches = ScheduleRecurrence::matches($template->frequency, $template->weekdays, $template->day_of_month, $instance->departure_date)
                        && $instance->departure_date->gte($template->starts_on)
                        && (! $template->ends_on || $instance->departure_date->lte($template->ends_on));

                    if (! $ruleMatches) {
                        $instance->update(['is_overridden' => true]);

                        return;
                    }

                    if ($instance->tickets()->whereIn('status', ['pending', 'issued', 'used'])->exists()) {
                        return;
                    }

                    $instance->update([
                        'departure_time' => $template->departure_time,
                        'arrival_time' => $template->arrival_time,
                        'available_seats' => $template->available_seats,
                    ]);
                });
        });
    }
}

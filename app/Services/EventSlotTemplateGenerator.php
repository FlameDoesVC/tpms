<?php

namespace App\Services;

use App\Models\EventSlotTemplate;
use App\Support\ScheduleRecurrence;
use Carbon\Carbon;

class EventSlotTemplateGenerator
{
    private const WINDOW_DAYS = 60;

    /**
     * Materializes missing EventSlot rows for every active template, up to
     * the rolling window (or the template's own ends_on, if sooner).
     */
    public function generate(): void
    {
        $windowEnd = Carbon::today()->addDays(self::WINDOW_DAYS);

        EventSlotTemplate::query()->where('is_active', true)->each(function (EventSlotTemplate $template) use ($windowEnd) {
            $start = Carbon::today()->max($template->starts_on);
            $end = $template->ends_on ? $windowEnd->copy()->min($template->ends_on) : $windowEnd;

            for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
                if (! ScheduleRecurrence::matches($template->frequency, $template->weekdays, $template->day_of_month, $date)) {
                    continue;
                }

                $exists = $template->instances()->whereDate('slot_date', $date->toDateString())->exists();

                if (! $exists) {
                    $template->instances()->create([
                        'event_id' => $template->event_id,
                        'slot_date' => $date->toDateString(),
                        'slot_time' => $template->slot_time,
                        'available_capacity' => $template->available_capacity,
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
     * an instance with active bookings is left alone rather than having its
     * live operational fields overwritten.
     */
    public function reconcile(): void
    {
        EventSlotTemplate::query()->where('is_active', true)->each(function (EventSlotTemplate $template) {
            $template->instances()
                ->where('is_overridden', false)
                ->whereDate('slot_date', '>=', Carbon::today())
                ->each(function ($instance) use ($template) {
                    $ruleMatches = ScheduleRecurrence::matches($template->frequency, $template->weekdays, $template->day_of_month, $instance->slot_date)
                        && $instance->slot_date->gte($template->starts_on)
                        && (! $template->ends_on || $instance->slot_date->lte($template->ends_on));

                    if (! $ruleMatches) {
                        $instance->update(['is_overridden' => true]);

                        return;
                    }

                    if ($instance->bookings()->whereIn('status', ['confirmed', 'used'])->exists()) {
                        return;
                    }

                    $instance->update([
                        'slot_time' => $template->slot_time,
                        'available_capacity' => $template->available_capacity,
                    ]);
                });
        });
    }
}

# Recurring Schedules (Ferries + Theme Park Events)

## Problem

`ferry_schedules` and `event_slots` are both one-row-per-instance tables with no
recurrence concept. Every ferry departure and every event slot must be created
manually, one at a time, even for trips/slots that repeat on a fixed daily,
weekly, or monthly pattern.

## Goals

- Let an admin define a recurring pattern once (daily / weekly-by-weekdays /
  monthly-by-fixed-day) and have concrete, bookable schedule/slot rows
  generated automatically.
- Keep per-instance mutable state (seats, status, tickets) exactly as it
  works today - recurrence only changes how rows get *created*, not how
  booking/seat-tracking works.
- Let admins still create one-off schedules/slots exactly as today.
- Let admins edit or cancel a single generated instance without affecting
  the rest of the series.
- Apply the same mechanism to both ferries and theme park events, since
  both currently share the identical one-row-per-instance limitation.

## Non-goals

- No RRULE/iCal-style recurrence (only daily / weekly-by-weekday-set /
  monthly-by-fixed-day-of-month are supported).
- No double-booking/conflict validation between overlapping templates -
  matches today's lack of conflict checks on manual schedule creation.
- No automatic ticket refund/notification flow for orphaned instances -
  those are flagged for manual admin review, never auto-cancelled.
- No support for a single template driving multiple ferries/events - one
  template belongs to exactly one ferry or one event.

## Data model

New table `ferry_schedule_templates` (and identically-shaped
`event_slot_templates` for theme park events):

```
id
ferry_id            -> foreignId, cascadeOnDelete   (event_id for the event variant)
frequency           enum('daily', 'weekly', 'monthly')
weekdays            json nullable   -- e.g. [1,3,5] for weekly (Mon/Wed/Fri); null otherwise
day_of_month        unsignedTinyInteger nullable  -- for monthly, e.g. 15
departure_time      time            (slot_time for the event variant)
arrival_time        time            (ferries only)
available_seats     unsignedInteger  (available_capacity for the event variant)
starts_on           date
ends_on             date nullable   -- null = generate indefinitely (rolling window keeps extending)
is_active           boolean default true  -- pause generation without deleting the template
timestamps
```

Existing `ferry_schedules` / `event_slots` gain:

```
template_id     foreignId nullable -> respective template table, nullOnDelete
is_overridden   boolean default false
```

`template_id = null` means a manually-created one-off row, unchanged from
today's behavior. `is_overridden = true` means "don't touch this row during
reconciliation" - set automatically whenever an admin directly edits or
cancels a template-generated instance, and also set when a template's rule
changes such that an already-generated instance no longer matches (see
Reconciliation below) - in that second case the row is also flagged for
admin review in the UI.

Rationale for structured columns over an RRULE string or JSON blob: the
three supported patterns are simple enough that a full recurrence-rule
parser (e.g. adding `simshaun/recurr`) would be over-engineering for the
actual requirement.

## Generation & reconciliation

New Artisan command `schedules:generate`, registered in the scheduler
(`app/Console/Kernel.php`) to run nightly via Laravel's existing scheduler
(the standard single `* * * * * php artisan schedule:run` cron entry
already required for this app's other scheduled behavior triggers it - no
new cron entry needed).

For each active template:

1. **Generate**: for each date in
   `[max(today, starts_on), min(ends_on ?? today+60, today+60)]` that
   matches the recurrence rule, create the corresponding instance row if one
   doesn't already exist for `(template_id, date)`. New rows copy
   `departure_time` / `arrival_time` / `available_seats` from the template
   and start as `status = 'scheduled'`.
2. **Reconcile**: for each future, non-overridden instance belonging to an
   active template:
   - If the template's core fields (time, seats) differ and the instance
     has zero tickets/bookings issued, sync them from the template.
   - If the instance has tickets/bookings issued, or the instance's date no
     longer matches the template's current rule (day dropped, range
     shrunk), leave the row untouched, set `is_overridden = true`, and
     surface it in the admin UI as "orphaned - review." Never auto-cancel
     a row with paying passengers.

Rule-matching is a single pure, unit-testable function shared by generate
and reconcile:

- `daily`: every date in range matches.
- `weekly`: date's ISO weekday is in `weekdays`.
- `monthly`: date's day-of-month equals `day_of_month` (months where that
  day doesn't exist, e.g. day 31 in February, simply produce no instance
  that month - no special-case handling needed).

Editing a template via the API triggers this same reconciliation logic
synchronously (not just via the nightly job), so admins see the effect of
a template edit immediately.

## API

New controller (`FerryScheduleTemplateController`, and the event-slot
equivalent), gated by the same policy role as manual schedule management
today (`ferry_operator` for ferries):

- `GET /api/ferry/schedule-templates` - list templates for a ferry
- `POST /api/ferry/schedule-templates` - create
- `PUT /api/ferry/schedule-templates/{id}` - update (triggers synchronous
  reconciliation)
- `DELETE /api/ferry/schedule-templates/{id}` - stops the series
  (implemented as `is_active = false`; existing generated instances are
  untouched and remain bookable)

Existing `FerryController::storeSchedule` / `updateSchedule` /
`destroySchedule` are unchanged for one-off use, except `updateSchedule`
and `destroySchedule` now set `is_overridden = true` on any instance that
has a non-null `template_id`.

## Frontend

`ScheduleManagementView.vue` (and the theme-park-event admin equivalent):

- Mode toggle when creating a schedule: "One-off" (today's flat form) vs.
  "Recurring" (frequency select, conditionally showing a weekday
  checkbox-set or a day-of-month number input, plus time/seats/date-range
  fields).
- View toggle: **List** (today's table, unchanged) vs. **Calendar** (new
  `MonthCalendar.vue`, a custom-built month grid - no new calendar library
  dependency). Each day cell shows compact chips per schedule/slot (time +
  seats remaining, colored by status); clicking a chip opens the same
  edit/cancel actions available in the list view. A badge distinguishes
  template-generated rows from one-off rows, and a distinct badge marks
  `is_overridden` rows flagged for review.
- New "Recurring Schedules" panel/tab listing templates: ferry/event,
  human-readable pattern summary (e.g. "Weekly: Mon/Wed/Fri, 09:00"),
  active toggle, edit, stop-series action.

Pinia store (`stores/ferry.js`, and the event-slot equivalent) gains
`fetchTemplates`, `createTemplate`, `updateTemplate`, `stopTemplate`.

## Testing

- Unit tests for the rule-matching function across daily/weekly/monthly,
  including month-length edge cases (day 31, Feb 29/30).
- Feature tests for `schedules:generate`: creates expected instances,
  doesn't duplicate on re-run, respects the rolling 60-day window.
- Feature tests for reconciliation: updates untouched instances, skips
  overridden ones, flags date-mismatched ones instead of deleting them.
- Feature test: booking a ticket against a template-generated instance,
  then editing the template, doesn't silently change or lose that
  ticket's schedule (instance becomes overridden instead).

# Sequential booking flow: theme park -> hotel -> ferry

## Problem

Each module's booking-confirmation page is a dead end — it links back to "my bookings" but gives no path forward into the next module. The visitor wants a guided chronological path: book a theme park event, then get nudged to book a hotel, then get nudged to book a ferry ticket.

## Constraint already in the codebase

Ferry ticket purchase already hard-requires a confirmed hotel booking (`FerryBookingView.vue`'s `hasConfirmedBooking` check, backed by a DB-transaction-gated rule server-side). Theme park and hotel bookings are otherwise fully independent of each other and of ferry — no backend gating between them today, and guest checkout relies on every module being independently browsable/bookable.

## Decision

This is a **suggestion, not an enforced gate**. No backend changes. Each confirmed-booking page gains a "book next" link to the *next* module's browse/list page — the visitor picks freely, same as browsing normally. Order: theme park -> hotel -> ferry (which already matches the existing ferry-requires-hotel rule, since hotel precedes ferry).

No reverse-direction nudges (e.g. hotel confirmation does not suggest theme park). No new state, no persisted "trip" concept, no check for bookings the visitor may already have elsewhere — the CTA is unconditional, matching the stateless style of the rest of the confirmation UI.

## Changes

1. **`resources/js/Pages/Visitor/EventDetailView.vue`** (theme-park booking confirmation): inside the existing `v-if="confirmedBooking"` green success box, alongside the current "View my bookings" link, add a "Book a hotel next" link to `{ name: 'hotels.index' }`.
2. **`resources/js/Pages/Visitor/BookingConfirmationView.vue`** (hotel booking confirmation): inside the existing `v-if="isConfirmed"` green success box, alongside "View my bookings", add a "Book a ferry ticket next" link to `{ name: 'ferry.book' }`.
3. **`resources/js/Pages/Visitor/FerryBookingView.vue`** (ferry ticket confirmation): no change — it's the last step in the chain and keeps its existing "View my tickets" link only.

## Testing

Frontend-only Vue template change (no test runner in this repo, per established practice) — verify via `npm run build` and a manual curl/click-through smoke check that the new links render and point to the right route names.

## Out of scope

- Enforcing order (blocking hotel booking until a theme-park booking exists, or ferry until hotel — the latter already exists and is untouched).
- A persisted trip/itinerary record.
- Reverse-direction nudges.
- Smart pre-selection of a specific hotel/ferry (links go to the plain browse/list page).

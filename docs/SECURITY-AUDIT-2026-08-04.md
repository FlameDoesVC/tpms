# TPMS — Security Audit Report

**Target:** TPMS (Tourism / Park Management System) — Laravel 13 API + Vue 3 SPA
**Commit audited:** `365521e` (branch `main`)
**Date:** 2026-08-04
**Method:** White-box source review of the entire application layer, plus dynamic
proof-of-concept exploitation against the real application stack (PHPUnit feature
tests, SQLite in-memory, full HTTP middleware pipeline), plus SCA of both
dependency trees.

Every finding marked **VERIFIED** below was reproduced by executing it against
the running application, not inferred from reading code. Raw output is quoted
verbatim in each finding. Appendix A contains the complete reproducer suite.

---

## 1. Executive summary

The application's **coarse role boundary is largely intact** — visitors cannot
reach admin/manager/operator endpoints, there is no SQL injection, no XSS sink,
no command execution, no mass-assignment escalation path, and Laravel's CSRF and
password-hashing defaults are correctly in place. The team's use of policies,
`lockForUpdate()` around capacity, and a service layer for booking logic is above
the level normally seen at this stage.

The weaknesses are concentrated in three systemic places:

1. **A second, unprotected authentication path.** `POST /api/guest/login` performs
   a full credential check with *no rate limiting at all*, defeating the 5-attempt
   lockout on `/login` and reaching any account including `admin`.
2. **Business-state integrity is enforced client-side, not server-side.** Payment,
   ticket validity, booking status transitions and cancellation idempotence are all
   trusted from the caller. A visitor can confirm their own unpaid booking,
   un-cancel a booking that has already been resold, and inflate park capacity
   without limit.
3. **Identity artefacts are guessable.** Ticket/booking QR codes are the row's
   auto-increment id (`VFN-B0001`, `VFN-T0002`, …) and are the *only* credential
   presented at the gate.

Additionally, no endpoint anywhere in the application is rate-limited, no security
response headers are set, and both dependency trees carry known-vulnerable
packages (24 Composer advisories, 7 npm advisories including 2 critical).

### Findings by severity

| Severity | Count | IDs |
|---|---|---|
| Critical | 1 | F-01 |
| High | 5 | F-02 … F-06 |
| Medium | 9 | F-07 … F-14, F-24 |
| Low | 9 | F-15 … F-23 |
| Informational | 1 | F-25 |

> Finding IDs are append-only. F-24 and F-25 were added during follow-up analysis
> on 2026-08-04 and appear in §2.1; earlier IDs were not renumbered.

| ID | Severity | Finding | OWASP 2021 | CWE |
|---|---|---|---|---|
| F-01 | **Critical** | `POST /api/guest/login` is an unthrottled credential-testing oracle that authenticates into any account, including `admin` | A07 | CWE-307 |
| F-02 | **High** | Visitor can self-confirm an unpaid booking; no payment is ever verified server-side | A04 / A01 | CWE-602, CWE-840 |
| F-03 | **High** | Ticket/booking reference codes are sequential ids used as the sole gate credential | A02 / A01 | CWE-330, CWE-639 |
| F-04 | **High** | Theme-park cancellation is not idempotent → unbounded capacity inflation | A04 | CWE-837 |
| F-05 | **High** | Un-cancelling a booking double-books a room already resold | A04 | CWE-372 |
| F-06 | **High** | Unauthenticated requests create permanent user accounts, unthrottled and never pruned | A04 | CWE-770 |
| F-07 | Medium | No rate limiting on any API or registration endpoint | A04 | CWE-770 |
| F-08 | Medium | Ferry ticket validation ignores the sailing and the date (client-side guard only) | A01 | CWE-602 |
| F-09 | Medium | Cancelled theme-park tickets validate as used | A04 | CWE-840 |
| F-10 | Medium | Authorization is ad-hoc and scattered; routes guarded only by `auth` | A01 | CWE-285 |
| F-11 | Medium | Known-vulnerable dependencies (24 Composer + 7 npm advisories) | A06 | CWE-1395 |
| F-12 | Medium | No security response headers (CSP, HSTS, nosniff, frame-options, …) | A05 | CWE-693 |
| F-13 | Medium | Account enumeration on password-reset and registration | A07 | CWE-204 |
| F-14 | Medium | Production-unsafe configuration defaults (`APP_DEBUG=true`, no `Secure` cookie, empty DB root password) | A05 | CWE-1188, CWE-16 |
| F-15 | Low | Email addresses are never verified; verification flow is dead code | A07 | CWE-1390 |
| F-16 | Low | `/confirm-password` is an unthrottled password oracle | A07 | CWE-307 |
| F-17 | Low | Wildcard CORS (`Access-Control-Allow-Origin: *`) on all `api/*` responses | A05 | CWE-942 |
| F-18 | Low | PII over-exposure: full user objects (emails) returned to staff endpoints | A01 | CWE-213 |
| F-19 | Low | No audit trail for privileged actions | A09 | CWE-778 |
| F-20 | Low | Guest→account claim does not regenerate the session | A07 | CWE-384 |
| F-21 | Low | Uploaded images served same-origin without `nosniff` or re-encoding | A05 | CWE-434 |
| F-22 | Low | `is_guest` is mass-assignable on `User` | A08 | CWE-915 |
| F-23 | Low | No MFA on `admin`/staff roles; `laravel/sanctum` installed but unused | A07 | CWE-308 |
| F-24 | Medium | `is_active` enforced inconsistently; the deliberate `?all=1` gate is bypassable via sibling endpoints | A01 | CWE-1230 |
| F-25 | Info | `AdminController::stats` uses MySQL-only `DATEDIFF`, so the endpoint cannot be tested | — | — |

---

## 2. Detailed findings

---

### F-01 — CRITICAL — `POST /api/guest/login` is an unthrottled credential oracle reaching admin

**Location:** `app/Http/Controllers/Api/GuestController.php:52-86`, `routes/web.php:67`
**OWASP:** A07 Identification & Authentication Failures · **CWE-307** Improper Restriction of Excessive Authentication Attempts
**Status:** VERIFIED

The primary login route is protected by a per-email+IP limiter (`LoginRequest::ensureIsNotRateLimited()`, 5 attempts). `GuestController::login()` reimplements credential verification by hand and inherits none of that protection:

```php
// GuestController.php:65-71
$target = User::where('email', $validated['email'])->first();

if (! $target || ! Hash::check($validated['password'], $target->password)) {
    throw ValidationException::withMessages([
        'email' => 'These credentials do not match our records.',
    ]);
}
// ... on success: Auth::login($target); session()->regenerate();
```

There is no `RateLimiter`, no lockout, no CAPTCHA, and no restriction on which account may be targeted. The route sits behind `auth`, but a session is free: `AutoLoginGuest` provisions and logs in a guest on any `POST` to `/api/bookings`, `/api/themepark/bookings` or `/api/cart/checkout` — **before** validation runs, so a deliberately malformed body works fine.

**Verified exploit chain:**

```
[PROBE2] /login locked out at attempt: 6
[PROBE2] /api/guest/login locked out at attempt: NULL   status counts={"422":30}
[PROBE2] correct password via /api/guest/login => HTTP 200

[PROBE16] guest-login against ADMIN account => HTTP 200; roles=["admin"]
[PROBE16] follow-up GET /api/admin/stats => HTTP 500   (reached the admin controller; the 500 is
                                                        MySQL-only DATEDIFF under SQLite — see F-25)
```

One request establishes the guest session; every subsequent request is an unlimited password guess. Thirty consecutive wrong passwords produced thirty `422`s and no lockout. The correct password authenticated the session — and when the target was an `admin`, the resulting session carried the `admin` role.

**Impact:** Online brute-force / credential-stuffing at unlimited rate against every account in the system, terminating in full administrative takeover (user CRUD, role assignment, all revenue data). The demo seeder ships every account with the password `password`, so in any environment seeded that way this is a single-request compromise.

**Remediation** — apply the same limiter, and restrict the target:

```php
// routes/web.php
Route::post('guest/login', [GuestController::class, 'login'])
    ->middleware('throttle:5,1');
```

```php
// GuestController::login()
$key = 'guest-login:'.Str::lower($validated['email']).'|'.$request->ip();

if (RateLimiter::tooManyAttempts($key, 5)) {
    throw ValidationException::withMessages([
        'email' => trans('auth.throttle', [
            'seconds' => RateLimiter::availableIn($key),
            'minutes' => ceil(RateLimiter::availableIn($key) / 60),
        ]),
    ]);
}

$target = User::where('email', $validated['email'])->first();

if (! $target || ! Hash::check($validated['password'], $target->password)) {
    RateLimiter::hit($key, 60);
    throw ValidationException::withMessages([
        'email' => 'These credentials do not match our records.',
    ]);
}

// A staff/admin account is never the destination of a guest-cart merge.
if (! $target->hasRole('visitor')) {
    abort(403);
}

RateLimiter::clear($key);
```

Better still: delete the hand-rolled check and delegate to `Auth::attempt()` inside a shared, throttled action so there is exactly one credential-verification path in the codebase.

---

### F-02 — HIGH — Visitor self-confirms an unpaid booking; payment is never verified server-side

**Location:** `app/Http/Controllers/Api/BookingController.php:66-80`; `app/Http/Controllers/Api/CartController.php:80`; `resources/js/Components/PaymentForm.vue:24-38`
**OWASP:** A04 Insecure Design / A01 Broken Access Control · **CWE-602** Client-Side Enforcement of Server-Side Security
**Status:** VERIFIED

`BookingController::update()` authorizes on *ownership* and then accepts any status the caller names:

```php
if ($booking->user_id !== $user->id && ! $user->hasRole('hotel_manager')) {
    abort(403);
}

$validated = $request->validate([
    'status' => ['required', 'in:confirmed,cancelled'],
]);

$booking->update($validated);
```

`confirmed` is the state that means *paid*. `FerryTicketService::issue()` gates ferry ticket purchase on exactly that state ("You need a confirmed hotel booking to purchase a ferry ticket"). So the transition a customer must never control is the one the endpoint hands them.

There is no payment record anywhere in the schema, and `PaymentForm.vue` states outright that card values "are deliberately never emitted" — the server only flips a status. `CartController::checkout()` calls `$this->hotelBookings->confirm($bookings)` unconditionally.

**Verified:**

```
[PROBE3] created status=pending; self-PATCH to confirmed => HTTP 200; now=confirmed
[PROBE3] ferry ticket after self-confirm => HTTP 201
```

A visitor created a `pending` booking, promoted it to `confirmed` themselves, and used it to obtain a ferry ticket — no payment step involved at any point.

**Impact:** Free hotel stays and free ferry tickets. Revenue reporting (`AdminController::stats`, which sums `status = 'confirmed'`) becomes attacker-controlled.

**Remediation:**

1. Remove `confirmed` from the visitor-reachable transition set. Let a visitor only *cancel*:

```php
$isStaff = $user->hasRole('hotel_manager');

$validated = $request->validate([
    'status' => ['required', $isStaff ? 'in:confirmed,cancelled' : 'in:cancelled'],
]);
```

2. Model the transition explicitly rather than accepting a target state (see F-05).
3. Introduce a `payments` table and make `confirmed` a consequence of a recorded, server-verified payment (gateway webhook, or an explicit staff cash-received action) — never of a client request. For coursework where a real gateway is out of scope, a server-side `Payment` row created by a `POST /api/payments` action with a simulated-but-server-owned reference is enough to make the boundary correct.

---

### F-03 — HIGH — Ticket and booking reference codes are sequential ids, used as the only gate credential

**Location:** `app/Models/Booking.php:27-30`, `app/Models/FerryTicket.php:33-36`, `app/Models/EventBooking.php:29-32`
**OWASP:** A02 Cryptographic Failures / A01 Broken Access Control · **CWE-330** Use of Insufficiently Random Values, **CWE-639** Authorization Bypass Through User-Controlled Key
**Status:** VERIFIED

```php
Attribute::get(fn () => sprintf('VFN-B%04d', $this->id));   // Booking
Attribute::get(fn () => sprintf('VFN-T%04d', $this->id));   // FerryTicket
Attribute::get(fn () => sprintf('VFN-E%04d', $this->id));   // EventBooking
```

These strings are rendered into the QR code the visitor presents (`TicketQr.vue`), and the staff scanners parse the trailing digits straight back into a primary key (`TicketValidationView.vue:115-122`, `parseBookingId`). The code carries no secret, no MAC, and no unpredictability — the entire keyspace is `1..n`.

**Verified:**

```
[PROBE7] consecutive hotel booking codes: VFN-B0001, VFN-B0002, VFN-B0003
[PROBE7] party lookup on guessed booking id 1 => HTTP 200
```

**Impact:** Three concrete abuses:

* **Ticket forgery / denial of boarding.** Anyone can render a QR for `VFN-T0007` and present it. The operator's screen shows a genuine, valid ticket and marks it `used`; the real holder is then refused with "already used".
* **Party hijack at the gate.** A guessed `VFN-B####` returns another party's full booking (`FerryController::partyStatus` → `$booking->load('room.hotel')`, guest headcount, existing tickets) and lets the operator issue walk-up seats against that party's remaining allowance (`issueWalkupTicket`, which bills `booking->user_id`, not the caller).
* **Business intelligence leakage.** Sequential ids disclose exact booking and ticket volumes to anyone holding one code.

**Remediation:** Make the presented code an opaque, unguessable, per-row secret, and stop resolving scans through the primary key:

```php
// migration
$table->string('reference_code', 32)->unique();

// model
protected static function booted(): void
{
    static::creating(function (self $ticket) {
        $ticket->reference_code ??= 'VFN-T'.Str::upper(Str::random(12));
    });
}
```

Then look up by `where('reference_code', $scanned)` instead of by id, and keep the numeric ids out of the QR payload entirely. If human-readable short codes must stay, sign them (`hash_hmac` over the id with `APP_KEY`, truncated) and verify the MAC before the lookup.

---

### F-04 — HIGH — Theme-park cancellation is not idempotent → unbounded capacity inflation

**Location:** `app/Http/Controllers/Api/ThemeParkController.php:223-237`
**OWASP:** A04 Insecure Design · **CWE-837** Improper Enforcement of a Single, Unique Action
**Status:** VERIFIED

```php
DB::transaction(function () use ($booking) {
    EventSlot::lockForUpdate()->findOrFail($booking->event_slot_id)
        ->increment('available_capacity', $booking->ticket_count);
    $booking->update(['status' => 'cancelled']);
});
```

The current status is never checked. The `lockForUpdate()` correctly serializes concurrent callers — but serialized repeats are still repeats. Note the contrast with `FerryController::cancelTicket()` (line 425-435), which *does* reject an already-cancelled ticket; the park path simply omits the equivalent guard.

**Verified** (slot starting at 8 available, one 2-ticket booking, event capacity 10):

```
[PROBE5] slot capacity after 5 repeated cancels of one 2-ticket booking:
         [10,12,14,16,18]   (event capacity_per_slot=10)
```

Five identical `DELETE` calls pushed the slot to 18 available seats against a real capacity of 10, and the loop has no bound.

**Impact:** Any visitor with one park booking can inflate any slot's capacity arbitrarily, causing systematic overselling — physical overcrowding at a real venue, plus refund liability. It also corrupts `capacityStatus` (`booked = capacity - available_capacity` goes negative) and the staff `WalkinSalesView` capacity checks.

**Remediation:**

```php
if ($booking->status === 'cancelled') {
    return response()->json($booking);          // already done; no side effect
}
if ($booking->status === 'used') {
    throw ValidationException::withMessages([
        'status' => 'This ticket has already been used and cannot be cancelled.',
    ]);
}
```

Additionally clamp the restore so a bug can never exceed the physical limit:

```php
$slot->update([
    'available_capacity' => min(
        $slot->event->capacity_per_slot,
        $slot->available_capacity + $booking->ticket_count
    ),
]);
```

Guard the same way in every other place a counter is restored, and consider deriving `available_capacity` from a `SUM` over live bookings rather than storing a mutable counter.

---

### F-05 — HIGH — Un-cancelling a booking double-books a room that has already been resold

**Location:** `app/Http/Controllers/Api/BookingController.php:66-80`, `app/Models/Room.php:44-56`
**OWASP:** A04 Insecure Design · **CWE-372** Incomplete Internal State Distinction
**Status:** VERIFIED

`Room::isAvailableBetween()` treats any non-cancelled overlapping booking as a conflict, so cancelling genuinely releases inventory. But the status endpoint accepts `confirmed` from *any* current state, including `cancelled` — there is no state machine.

**Verified:**

```
[PROBE17] second visitor books the released room => HTTP 201
[PROBE17] un-cancel cancelled booking => HTTP 200; active bookings on the same room/dates now = 2
```

Visitor A booked and cancelled; visitor B legitimately took the freed room; visitor A flipped their old booking back to `confirmed`. Two active bookings now exist on one room for the same nights, and the availability check that should have prevented it was never consulted.

**Impact:** Deterministic double-booking of physical rooms — two paying guests, one bed, at check-in. Also re-opens ferry-ticket allowance tied to the resurrected booking's party headcount.

**Remediation:** Replace target-state assignment with explicit, validated transitions:

```php
private const TRANSITIONS = [
    'pending'   => ['confirmed', 'cancelled'],
    'confirmed' => ['cancelled'],
    'cancelled' => [],            // terminal
];

$next = $validated['status'];

if (! in_array($next, self::TRANSITIONS[$booking->status] ?? [], true)) {
    throw ValidationException::withMessages([
        'status' => "A {$booking->status} booking cannot become {$next}.",
    ]);
}
```

If re-activation is a genuine business need, it must be a fresh booking that re-runs `isAvailableBetween()` under `lockForUpdate()`.

---

### F-06 — HIGH — Unauthenticated requests create permanent user accounts, unthrottled and never pruned

**Location:** `app/Http/Middleware/AutoLoginGuest.php:19-27`, `app/Models/User.php:41-53`, `routes/web.php:51-55`
**OWASP:** A04 Insecure Design · **CWE-770** Allocation of Resources Without Limits
**Status:** VERIFIED

```php
if (! Auth::check()) {
    Auth::login(User::createGuest());
    $request->session()->regenerate();
}
```

This runs before controller validation on three public `POST` routes, so the write happens even when the request body is garbage. Each call inserts a `users` row (plus a spatie `model_has_roles` row and a `sessions` row). Nothing throttles it and nothing ever deletes abandoned guests.

**Verified:**

```
[PROBE9] users created by 10 cookieless POSTs: 10 (guests: 10)
[PROBE1] users before=0 after=1 guests=1   (session reused → one row, confirming the per-session model)
```

**Impact:** Unauthenticated storage-exhaustion / cost DoS — a trivial loop writes rows at request rate across three tables. It also pollutes `AdminController::stats` (`users.total`, `users.guests`), degrades `User::count()`-style queries, and makes the `users` table unusable as a business metric. The `guest-<uuid>@guest.tpms.local` addresses are permanently unique, so the table only grows.

**Remediation:**

1. Throttle the group: `Route::middleware([AutoLoginGuest::class, 'throttle:10,1'])`. Order matters — put `throttle` **first** so rejected requests never provision:
   ```php
   Route::middleware(['throttle:10,1', AutoLoginGuest::class])->prefix('api')->group(...);
   ```
2. Defer provisioning until the payload is known-good — validate in a `FormRequest` and create the guest inside the controller, not in middleware.
3. Prune abandoned guests on a schedule:
   ```php
   Schedule::call(fn () => User::where('is_guest', true)
       ->where('created_at', '<', now()->subDays(7))
       ->whereDoesntHave('bookings')->delete())->daily();
   ```
4. Consider not creating a `User` at all until checkout succeeds — hold cart identity in the session and materialize the account in the same transaction as the booking.

---

### F-07 — MEDIUM — No rate limiting on any API or registration endpoint

**Location:** `routes/web.php` (all 85 route declarations), `routes/auth.php:13-29`, `bootstrap/app.php:14-18`
**OWASP:** A04 · **CWE-770**
**Status:** VERIFIED

`throttle` appears exactly twice in the codebase — on `verification.verify` and `verification.send` (`routes/auth.php:33,37`), both of which are dead code (see F-15). No API route, and no registration or password-reset route, carries any limiter. `bootstrap/app.php` defines no global rate limiter either.

**Verified:**

```
[PROBE8]  60 rapid /api/hotels requests, distinct statuses: [200]
[PROBE11] 25 registrations => {"201":25} ; users now=25
```

**Impact:** Enables F-01 and F-06 at scale; permits scraping of every public listing endpoint, mass account creation, and cheap application-layer DoS on the expensive endpoints (`AdminController::stats` runs eight aggregate queries including two multi-table joins with `DATEDIFF`).

**Remediation:**

```php
// bootstrap/app.php
->withMiddleware(function (Middleware $middleware) {
    $middleware->web(append: [\Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class]);
    $middleware->throttleApi('120,1');   // or a named limiter below
})
```

```php
// AppServiceProvider::boot()
RateLimiter::for('api', fn (Request $r) => Limit::perMinute(120)->by($r->user()?->id ?: $r->ip()));
RateLimiter::for('auth', fn (Request $r) => Limit::perMinute(5)->by($r->ip()));
RateLimiter::for('writes', fn (Request $r) => Limit::perMinute(20)->by($r->user()?->id ?: $r->ip()));
```

Apply `throttle:auth` to `login`, `register`, `forgot-password`, `reset-password`, `confirm-password` and `api/guest/login`; `throttle:writes` to the booking/checkout group; `throttle:api` to the rest.

---

### F-08 — MEDIUM — Ferry ticket validation ignores the sailing and the date

**Location:** `app/Http/Controllers/Api/FerryController.php:396-417`; client-side guard at `resources/js/Pages/Ferry/TicketValidationView.vue:236-239`
**OWASP:** A01 · **CWE-602** Client-Side Enforcement of Server-Side Security
**Status:** VERIFIED

The server checks only that the ticket is not already `used` or `cancelled`. It never checks that the ticket belongs to the departure being boarded, nor that the departure is today, nor that the schedule status is `scheduled`. The only such check lives in the SPA:

```js
// TicketValidationView.vue — the app's own comment concedes this is the control
// "A ticket can be looked up regardless of which departure is selected ...
//  this is what actually stops it from being confirmed against the wrong one."
const scheduleMismatch = computed(() =>
    !!(ticket.value && selectedSchedule.value && ticket.value.schedule_id !== selectedSchedule.value.id)
);
```

**Verified:**

```
[PROBE4] validate 10-day-old ticket from another sailing => HTTP 200; status=used
```

**Impact:** A ticket for a different or long-past crossing validates successfully. Any direct API call (or a modified/older client, or a staff member clicking through) boards a passenger on the wrong boat, corrupts the manifest used for headcount and safety, and consumes a ticket the owner still holds. Exploitable in combination with F-03: forge `VFN-T####`, board any sailing.

**Remediation:**

```php
public function validateTicket(Request $request, FerryTicket $ticket): JsonResponse
{
    if (! $request->user()->hasAnyRole(['ferry_operator', 'admin'])) {
        abort(403);
    }

    $validated = $request->validate([
        'schedule_id' => ['required', 'exists:ferry_schedules,id'],
    ]);

    if ((int) $ticket->schedule_id !== (int) $validated['schedule_id']) {
        throw ValidationException::withMessages([
            'schedule_id' => 'This ticket is for a different departure.',
        ]);
    }

    $schedule = $ticket->schedule;

    if ($schedule->status !== 'scheduled') {
        throw ValidationException::withMessages(['schedule_id' => 'This departure is not boarding.']);
    }
    if (! $schedule->departure_date->isToday()) {
        throw ValidationException::withMessages(['schedule_id' => 'This ticket is not for today.']);
    }
    // ... existing used/cancelled guards
}
```

Have the SPA send the selected `schedule_id`, and keep `scheduleMismatch` purely as UX. Apply the same date/slot scoping to `ThemeParkTicketController::validateTicket`.

---

### F-09 — MEDIUM — Cancelled theme-park tickets validate as used

**Location:** `app/Http/Controllers/Api/ThemeParkTicketController.php:59-74`
**OWASP:** A04 · **CWE-840** Business Logic Errors
**Status:** VERIFIED

```php
if ($booking->status === 'used') {
    throw ValidationException::withMessages(['status' => 'This ticket has already been used.']);
}

$booking->update(['status' => 'used']);
```

Only `used` is rejected. `cancelled` is not — again in contrast to the ferry equivalent, which checks both.

**Verified:**

```
[PROBE6] validate CANCELLED park booking => HTTP 200; status=used
```

**Impact:** A refunded or cancelled ticket still admits the holder. Because cancellation already returned the seat to inventory (F-04), the slot is now oversold by exactly the admitted party, and revenue reporting counts the entry twice (`dailySales` excludes `cancelled`, so flipping to `used` silently re-adds it).

**Remediation:** Reject `cancelled`, and scope validation to the slot's own date/status as in F-08:

```php
if ($booking->status === 'cancelled') {
    throw ValidationException::withMessages(['status' => 'This ticket has been cancelled.']);
}
if ($booking->slot->status !== 'scheduled') {
    throw ValidationException::withMessages(['status' => 'This time slot is not running.']);
}
```

---

### F-10 — MEDIUM — Authorization is ad-hoc and scattered; routes guarded only by `auth`

**Location:** `routes/web.php:57-142`; 24 inline role checks across `app/Http/Controllers/`; `app/Policies/` (6 policies for ~15 resources)
**OWASP:** A01 · **CWE-285** Improper Authorization
**Status:** Confirmed by inspection

Every privileged route — including all of `admin/users`, `admin/stats`, `map/locations`, `promotions`, the ferry fleet, the ticket scanners — sits inside a single `Route::middleware('auth')` group. No `role:` or `can:` middleware is used anywhere. Authorization is therefore entirely a property of each controller method body, in three inconsistent styles:

| Style | Count | Example |
|---|---|---|
| `Gate::authorize(...)` + policy | 27 calls | `HotelController::store` |
| Inline `hasRole` / `hasAnyRole` + `abort(403)` | 24 calls | `ThemeParkTicketController` (6×), `FerryController` (5×) |
| Private helper (`authorizeAdmin`, `canManage`) | 2 | `AdminController`, `PromotionController` |

`spatie/laravel-permission` is installed but only its *roles* are used; no permission is ever defined or checked, so every capability is hard-coded as a role-name string literal duplicated across files. Adding a route or a method is one forgotten line away from full public exposure — and a role rename is a silent, codebase-wide authorization failure with no test to catch it.

I verified the current checks do hold at the boundary (a `visitor` gets `403` from `/api/admin/users`, `/api/themepark/tickets/sell`, `/api/ferry/passengers`), so this is a **latent** rather than active exposure. It is nonetheless the finding most likely to *become* a breach as the system grows.

**Remediation:**

1. Push the coarse boundary into the route definition so it cannot be omitted:
   ```php
   Route::middleware(['auth', 'role:admin'])->prefix('api/admin')->group(function () {
       Route::get('stats', [AdminController::class, 'stats']);
       Route::apiResource('users', AdminController::class);
   });
   Route::middleware(['auth', 'role:ferry_operator|admin'])->prefix('api/ferry')->group(...);
   ```
2. Replace role-name literals with named permissions (`tickets.validate`, `fleet.manage`, `users.manage`) seeded onto roles; check `$user->can('tickets.validate')`. A role rename then touches one seeder.
3. Complete the policy set (Booking, EventBooking, FerryTicket, MapLocation, Promotion, User) and standardize on `Gate::authorize` / `$this->authorize`, deleting the inline `hasAnyRole` checks.
4. Add a negative-authorization test matrix — for each role × each privileged route, assert `403`. This is cheap and would have made F-01 visible immediately.

---

### F-11 — MEDIUM — Known-vulnerable dependencies

**Location:** `composer.lock`, `package-lock.json`
**OWASP:** A06 Vulnerable and Outdated Components · **CWE-1395**
**Status:** VERIFIED (`composer audit`, `npm audit`)

**Composer — 24 advisories across 9 packages:**

| Package | Installed | Advisories | Notable |
|---|---|---|---|
| `guzzlehttp/guzzle` | 7.10.0 | 9 | **high** CVE-2026-69246; CVE-2026-69245, CVE-2026-59883, CVE-2026-55767, CVE-2026-55568 |
| `guzzlehttp/psr7` | 2.9.0 | 4 | CVE-2026-59882, CVE-2026-55766, CVE-2026-49214, CVE-2026-48998 |
| `laravel/framework` | v13.4.0 | 3 | **high** GHSA-5vg9-5847-vvmq / CVE-2026-48019 |
| `symfony/routing` | v8.0.8 | 2 | CVE-2026-48784, CVE-2026-45065 (off-site `//host` URL injection via UrlGenerator) |
| `symfony/mime` | v8.0.8 | 2 | — |
| `symfony/http-foundation`, `symfony/http-*`, `symfony/mailer`, `symfony/polyfill-*` | v8.0.8 | 1 each | — |

**npm — 7 vulnerabilities (2 critical, 4 high, 1 moderate):**

| Package | Severity | Notable |
|---|---|---|
| `shell-quote` (via `concurrently`) | **critical** | GHSA-w7jw-789q-3m8p |
| `axios` 1.14.0 (dev-pinned `>=1.11.0 <=1.14.0`) | high | 28 advisories incl. prototype-pollution → credential theft / request hijacking, XSRF-token cross-origin leakage (GHSA-xx6v-rp6x-q39c) |
| `postcss` | high | XSS via unescaped `</style>`; arbitrary `.map` file read |
| `vite` 8.0.x | high | `server.fs.deny` bypass on Windows alternate paths |
| `form-data` | high | CRLF injection |
| `follow-redirects` | moderate | auth-header leak across cross-domain redirect |

Note `axios` is the one that matters at runtime — it is the SPA's HTTP client and the XSRF-token leakage advisory is directly relevant to this app's cookie-based CSRF scheme. The `package.json` constraint `"axios": ">=1.11.0 <=1.14.0"` actively pins it below the fix.

**Remediation:**

```bash
composer update --with-all-dependencies      # then re-run: composer audit
npm audit fix                                 # postcss, form-data, follow-redirects, vite, shell-quote
```
Then lift the axios ceiling in `package.json` (`"axios": "^1.19.0"`) and re-run `npm audit`. The `vite`/`postcss`/`shell-quote`/`concurrently` issues are dev-time only but still belong in the fix list. Add `composer audit` + `npm audit --omit=dev` to CI as a failing gate.

---

### F-12 — MEDIUM — No security response headers

**Location:** `bootstrap/app.php:14-18` (middleware stack)
**OWASP:** A05 Security Misconfiguration · **CWE-693** Protection Mechanism Failure
**Status:** VERIFIED

```
[PROBE14] {"Content-Security-Policy":"MISSING","X-Frame-Options":"MISSING",
           "X-Content-Type-Options":"MISSING","Referrer-Policy":"MISSING",
           "Strict-Transport-Security":"MISSING","Permissions-Policy":"MISSING"}
```

**Impact:** No defence-in-depth if an XSS sink is ever introduced (none exists today — see §3); the app is framable (clickjacking against admin actions); MIME sniffing amplifies F-21; full URLs leak in `Referer` to the third-party font host `fonts.bunny.net`; no HSTS to prevent SSL-strip on the session cookie (which also lacks `Secure`, F-14).

**Remediation:** Add a middleware appended to the `web` group:

```php
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->add([
            'X-Frame-Options' => 'DENY',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'geolocation=(), microphone=(), camera=(self)', // camera: QR scanner
            'Content-Security-Policy' => implode('; ', [
                "default-src 'self'",
                "img-src 'self' data: https://*.tile.openstreetmap.org https://server.arcgisonline.com",
                "style-src 'self' 'unsafe-inline' https://fonts.bunny.net",
                "font-src 'self' https://fonts.bunny.net",
                "connect-src 'self'",
                "frame-ancestors 'none'",
                "base-uri 'self'",
                "form-action 'self'",
            ]),
        ]);

        return $response;
    }
}
```

Add `Strict-Transport-Security: max-age=31536000; includeSubDomains` at the TLS terminator in production. Note the inline theme script in `app.blade.php:16-22` and Leaflet's inline styles will need either a nonce or the `'unsafe-inline'` shown above — prefer a nonce for `script-src`.

---

### F-13 — MEDIUM — Account enumeration on password reset and registration

**Location:** `app/Http/Controllers/Auth/PasswordResetLinkController.php:22-31`; `app/Http/Controllers/Auth/RegisteredUserController.php:26`
**OWASP:** A07 · **CWE-204** Observable Response Discrepancy
**Status:** VERIFIED

```
[PROBE10] known email   => HTTP 200 {"status":"We have emailed your password reset link."}
[PROBE10] unknown email => HTTP 422 {"message":"We can't find a user with that email address."}
[PROBE10] register with existing email => HTTP 422 {"email":["The email has already been taken."]}
```

**Impact:** An attacker can confirm which email addresses hold accounts before spending guesses — which, combined with the unlimited guessing in F-01, turns credential stuffing from noisy to precise. Also a privacy disclosure in its own right (reveals that a named person is a customer).

**Remediation:** Return an identical response either way:

```php
Password::sendResetLink($request->only('email'));

return response()->json([
    'status' => __('If that email address is registered, a reset link is on its way.'),
]);
```

Registration cannot be made fully silent without changing the UX; the standard mitigation is to keep the "already taken" message but put `throttle:auth` in front of the route (F-07) so enumeration cannot be automated at volume.

---

### F-14 — MEDIUM — Production-unsafe configuration defaults

**Location:** `.env.example:4,26-27`; `compose.yaml:33-42`; `config/session.php` (`secure`, `encrypt`); local `.env:4`
**OWASP:** A05 · **CWE-1188** Insecure Default Initialization, **CWE-16** Configuration
**Status:** Confirmed by inspection + VERIFIED cookie attributes

Positive first: **`.env` is correctly git-ignored and has never been committed** (`git log --all -- .env` is empty; only `.env.example` is tracked). No credentials, keys or tokens were found anywhere in the repository or its history.

The issues are in the defaults a deployer inherits:

| Setting | Current | Risk |
|---|---|---|
| `APP_DEBUG=true` in `.env.example` (and local `.env`) | Whoops/Ignition stack traces, env dump, DB credentials on any 500 | The 500 in F-01's probe shows unhandled exceptions do occur |
| `SESSION_SECURE_COOKIE` unset | Session cookie has **no `Secure` flag** — verified: `tpms-session=...; path=/; httponly; samesite=lax` | Session theft over plain HTTP |
| `SESSION_ENCRYPT=false` | Session payload stored unencrypted | Lower impact (DB-backed), but free to enable |
| `.env.example`: `DB_USERNAME=root`, `DB_PASSWORD=` | Empty-password root DB by default | — |
| `compose.yaml:40`: `MYSQL_ALLOW_EMPTY_PASSWORD: 1` + `MYSQL_ROOT_HOST: '%'` + `ports: ${FORWARD_DB_PORT}:3306` | Root from any host, empty password permitted, port published to the host | Sail default, but a real exposure if ever run on a non-loopback interface |
| `LOG_LEVEL=debug` | Verbose logs incl. request context | Low |

**Remediation:**

* `.env.example`: `APP_DEBUG=false`, `LOG_LEVEL=warning`, `SESSION_SECURE_COOKIE=true`, `SESSION_ENCRYPT=true`, and a non-root `DB_USERNAME` with a placeholder password.
* Add a deployment checklist to `README.md`: `APP_DEBUG=false`, `APP_ENV=production`, unique `APP_KEY`, HTTPS-only, `php artisan config:cache route:cache`.
* Add a startup assertion so a debug-mode production deploy fails loudly:
  ```php
  // AppServiceProvider::boot()
  if (app()->isProduction() && config('app.debug')) {
      throw new RuntimeException('APP_DEBUG must be false in production.');
  }
  ```
* Bind the MySQL port to loopback (`127.0.0.1:${FORWARD_DB_PORT}:3306`) and drop `MYSQL_ALLOW_EMPTY_PASSWORD`.

---

### F-15 — LOW — Email addresses are never verified; the verification flow is dead code

**Location:** `app/Models/User.php:5` (`// use ...MustVerifyEmail;`), `routes/auth.php:31-38`
**OWASP:** A07 · **CWE-1390** Weak Authentication
**Status:** VERIFIED

`User` does not implement `MustVerifyEmail`, so Laravel's `SendEmailVerificationNotification` listener no-ops on `Registered`. The `verification.verify` / `verification.send` routes, `VerifyEmailController`, and `Pages/Auth/VerifyEmail.vue` are unreachable in practice, and no route anywhere uses the `verified` middleware.

```
[PROBE18] register => HTTP 201; implements MustVerifyEmail=false; email_verified_at=NULL
[PROBE18] no verification notification was sent (assertNothingSent passed)
[PROBE18] unverified account books a room => HTTP 201
```

**Impact:** Accounts can be created and transact under addresses the registrant does not control — enabling booking under someone else's identity, and making the email channel unusable for security notices (which matters for F-19). Also inflates the account-creation abuse in F-07.

**Remediation:** Implement the contract and gate the transactional routes:

```php
class User extends Authenticatable implements MustVerifyEmail
```
```php
Route::middleware(['auth', 'verified'])->group(function () { /* booking/checkout writes */ });
```
Guest-checkout accounts need a deliberate exemption path — verify at the point the guest claims the account (`GuestController::claim`) rather than blocking the anonymous flow.

---

### F-16 — LOW — `/confirm-password` is an unthrottled password oracle

**Location:** `app/Http/Controllers/Auth/ConfirmablePasswordController.php:15-29`, `routes/auth.php:40`
**OWASP:** A07 · **CWE-307**
**Status:** VERIFIED

```
[PROBE19] 25 wrong /confirm-password attempts => {"422":25}
```

Requires an authenticated session, so the practical use is confirming a password for a hijacked session (e.g. a borrowed browser) rather than remote brute force — hence Low. Same fix as F-07: `->middleware('throttle:6,1')`.

---

### F-17 — LOW — Wildcard CORS on all `api/*` responses

**Location:** no `config/cors.php` → framework default `allowed_origins => ['*']`, `paths => ['api/*']`
**OWASP:** A05 · **CWE-942** Overly Permissive Cross-domain Policy
**Status:** VERIFIED

```
[PROBE13] /api/promotions with hostile Origin => HTTP 200; ACAO='*'; ACAC=NULL
```

Because `supports_credentials` is `false`, the wildcard cannot be combined with cookies — a cross-origin `fetch(..., {credentials:'include'})` is rejected by the browser, so **authenticated data does not leak and CSRF protection is unaffected**. The exposure is limited to endpoints that are already public. Severity is Low for that reason, but the configuration is broader than intended and should be pinned before anything sensitive is added under `api/*`.

**Remediation:** publish an explicit policy —

```php
// config/cors.php
'paths' => ['api/*'],
'allowed_methods' => ['GET', 'POST', 'PATCH', 'DELETE'],
'allowed_origins' => [env('APP_URL', 'http://localhost')],
'supports_credentials' => false,
```

---

### F-18 — LOW — PII over-exposure in staff endpoints

**Location:** `BookingController::index:18`; `FerryController::showTicket:393`, `passengers:488`, `partyStatus:470`; `ThemeParkTicketController::showTicket:56`
**OWASP:** A01 · **CWE-213** Exposure of Sensitive Information Due to Incompatible Policies
**Status:** Confirmed by inspection

These endpoints eager-load `'user'` and serialize the whole model. `$hidden` covers `password` and `remember_token`, so no credential material leaks — but every response carries each customer's `email`, `is_guest`, `email_verified_at` and timestamps to any holder of the relevant staff role. A gate scanner needs a name and a seat, not an email address; `BookingController::index` returns the full user object for *every* booking in the system to any `hotel_manager`.

**Remediation:** Return API Resources scoped to purpose rather than raw models — the codebase already has `HotelResource`/`RoomResource` to follow:

```php
$query->with(['room.hotel', 'user:id,name']);          // minimal projection
// or a PassengerResource exposing only { name, seat_number, reference_code, status }
```

---

### F-19 — LOW — No audit trail for privileged actions

**Location:** application-wide; no logging in `AdminController`, `FerryController::validateTicket/cancelTicket`, `ThemeParkTicketController`, `PromotionController`
**OWASP:** A09 Security Logging and Monitoring Failures · **CWE-778** Insufficient Logging
**Status:** Confirmed by inspection (`grep` for `Log::` in `app/` returns nothing)

Nothing records who validated a ticket, cancelled a booking, changed a user's role, deleted a user, reshaped a ferry deck, or sold a walk-up ticket. `ferry_tickets` has no `validated_by`/`validated_at`; `event_bookings` has no `cancelled_by`. None of the exploits proven in F-01 through F-06 would leave any trace.

**Remediation:** Persist actor + timestamp on the affected rows (`validated_by`, `validated_at`, `cancelled_by`, `cancelled_at`) and emit structured logs for privileged mutations:

```php
Log::channel('audit')->info('ferry.ticket.validated', [
    'ticket_id' => $ticket->id, 'schedule_id' => $ticket->schedule_id,
    'actor_id' => $request->user()->id, 'ip' => $request->ip(),
]);
```
Add a dedicated `audit` channel in `config/logging.php`. Also log authentication failures and lockouts (`Illuminate\Auth\Events\Failed`, `Lockout`) — without this, the brute force in F-01 is invisible.

---

### F-20 — LOW — Guest→account claim does not regenerate the session

**Location:** `app/Http/Controllers/Api/GuestController.php:24-46`
**OWASP:** A07 · **CWE-384** Session Fixation
**Status:** Confirmed by inspection

`claim()` mutates the current user row into a real account (name, email, hashed password, `is_guest = false`) and returns, without calling `$request->session()->regenerate()`. `AutoLoginGuest` did regenerate when the guest was created, and `login()` regenerates — `claim()` is the one path that does not.

**Impact:** An attacker who plants a known guest-session cookie in a victim's browser (via a subdomain cookie-injection, a shared machine, or a physical handoff) retains a valid session on the resulting *real* account after the victim registers through the claim flow, including the password they just set. The prerequisite makes this Low.

**Remediation:**

```php
$user->update([...]);
$request->session()->regenerate();     // privilege level changed
```

Regenerate on every privilege transition as a rule.

---

### F-21 — LOW — Uploaded images served same-origin without `nosniff` or re-encoding

**Location:** `ThemeParkController::store:73-76`/`update:99-103`, `PromotionController` equivalents; `config/media-library.php:36` (`disk_name => public`); `config/filesystems.php` public disk
**OWASP:** A05 / A03 · **CWE-434** Unrestricted Upload of File with Dangerous Type
**Status:** Confirmed by inspection

Validation is reasonable — `['nullable','image','mimes:jpeg,png,webp,gif','max:5120']` runs `getimagesize()` and excludes SVG (the usual stored-XSS vector), and spatie stores under a generated path so the original filename is not the URL. Files are, however, served from the application's own origin (`/storage/...`) with no `X-Content-Type-Options: nosniff` (F-12) and are stored byte-for-byte without re-encoding, so a polyglot that satisfies `getimagesize()` while containing markup relies solely on the server's `Content-Type` for safety.

**Remediation:** Add `nosniff` (F-12); strip metadata / re-encode on upload via a spatie conversion; and prefer serving user content from a separate origin or a signed-URL route if the deployment allows.

---

### F-22 — LOW — `is_guest` is mass-assignable on `User`

**Location:** `app/Models/User.php:16` — `#[Fillable(['name','email','password','is_guest'])]`
**OWASP:** A08 · **CWE-915**
**Status:** VERIFIED not currently exploitable

```
[PROBE12] profile PATCH with extra fields => HTTP 200; is_guest=false; role=visitor
[PROBE12] non-admin PATCH /api/admin/users => HTTP 403
```

Every write path filters through a validator whose rule set excludes `is_guest`, so there is no live exploit — `ProfileUpdateRequest` returns only `name`/`email`, and `AdminController::update` only `name`/`email`/`password`/`role`. It remains a loaded gun: any future `User::create($request->all())` or added `sometimes` rule grants a caller the ability to mark themselves a guest (which then unlocks `guest/claim` and `guest/login` — see F-01) or to un-mark a guest.

**Remediation:** Remove `is_guest` from `Fillable` and set it explicitly in `User::createGuest()` and `GuestController::claim()` via `forceFill()` or direct assignment.

---

### F-23 — LOW — No MFA on privileged roles; unused auth dependency

**Location:** `config/auth.php`; `composer.json` (`laravel/sanctum ^4.0`)
**OWASP:** A07 · **CWE-308** Use of Single-factor Authentication
**Status:** Confirmed by inspection

The `admin` role can create users, assign any role, delete accounts and read all revenue data, protected by a single password (and, per F-01, an unlimited number of guesses at it). No second factor exists.

Separately, `laravel/sanctum` is installed and migrated but no route, model trait (`HasApiTokens`) or middleware uses it — the SPA authenticates purely by session cookie. Unused authentication surface is worth removing rather than leaving for a future contributor to wire up incorrectly.

**Remediation:** Add TOTP for `admin` and staff roles (`laravel/fortify` two-factor, or `pragmarx/google2fa-laravel`). Remove `laravel/sanctum` from `composer.json` unless a token-auth use case is planned.

---

## 2.1 Findings added during follow-up analysis

### F-24 — MEDIUM — `is_active` enforced inconsistently; the `?all=1` gate is bypassable via sibling endpoints

**Location:** `HotelController::index:23`, `show:43`; `FerryController::schedules:240`; `ThemeParkController::allSlots:126`, `show:41`; contrast with `HotelController::popular:34`, `ThemeParkController::index:23`, `FerryController::ferries:36`
**OWASP:** A01 Broken Access Control · **CWE-1230** Exposure of Sensitive Information Through Metadata
**Status:** VERIFIED

`is_active` is the application's "take it off sale" control, and it is honoured on some public endpoints and silently ignored on adjacent ones returning the same data.

`FerryController::ferries()` is the clearest case. It deliberately gates the unfiltered view, with a comment explaining exactly why:

```php
// "`?all=1` is for the management screen and needs a signed-in operator, since a
//  retired ferry is not something a visitor should be able to enumerate."
if ($wantsAll) {
    Gate::authorize('create', Ferry::class);
}
```

That gate works — and the same data walks straight out of `GET /api/ferry/schedules`, which eager-loads `ferry` with no filter at all.

**Verified:**

```
[PROBE20] GET /api/hotels (unauthenticated) => ["Live Hotel","Retired Hotel"]
[PROBE20] GET /api/hotels/{inactive id} => HTTP 200; is_active=false
[PROBE20] popular() (which DOES filter) => ["Live Hotel"]

[PROBE21] GET /api/ferries?all=1 (anon) => HTTP 403          <-- gate works
[PROBE21] GET /api/ferries (anon) => []                       <-- correctly hidden
[PROBE21] GET /api/ferry/schedules (anon) leaks ferry => ["Retired Boat"] is_active=[false]

[PROBE22] GET /api/themepark/events (anon) => []              <-- filtered
[PROBE22] GET /api/themepark/slots (anon) leaks event => ["Unannounced Event"] is_active=[false]
[PROBE22] GET /api/themepark/events/{inactive id} (anon) => HTTP 200; name="Unannounced Event"
```

**Impact:** Retired hotels and ferries, and — most sensitively — **unannounced theme-park events with their full scheduling calendar**, are readable by anonymous visitors. For a tourism operator, an unreleased event name and date is commercially confidential (competitor intelligence, embargoed marketing). `themepark/slots` is described in its own docblock as being "for the staff scheduling calendar" yet is registered in the public route group (`routes/web.php:43`).

**A coupled functional bug:** `resources/js/stores/themepark.js:41` — the staff `EventManagementView` and the visitor home both call the *same* filtered `GET /api/themepark/events`. So once staff toggle an event inactive it disappears from their own management list and cannot be toggled back. Any fix must add a management scope, not just a filter.

**Remediation** — put the rule in one place per model and add a role-gated management scope, following the `?all=1` precedent the codebase already established:

```php
// app/Models/Concerns/HasActiveScope.php (or per-model)
public function scopeVisibleTo(Builder $query, ?User $user, bool $wantsAll = false): Builder
{
    if ($wantsAll && $user?->hasAnyRole(['hotel_manager', 'themepark_staff', 'ferry_operator', 'admin'])) {
        return $query;
    }
    return $query->where('is_active', true);
}
```

Then: filter `HotelController::index`/`show`, constrain the `ferry` eager-load in `schedules()` to active boats, move `themepark/slots` into the `auth` group behind a staff role, and reject `show()` for inactive records unless the caller is staff. Update `fetchEvents`/`fetchHotels`/`fetchAllSlots` in the Pinia stores to pass the management flag on staff screens — which also repairs the one-way toggle above.

---

### F-25 — INFORMATIONAL — `AdminController::stats` is not portable and therefore untestable

**Location:** `app/Http/Controllers/Api/AdminController.php:126,156`
**Status:** VERIFIED (observed as the HTTP 500 in F-01's probe)

`SUM(rooms.price_per_night * DATEDIFF(bookings.check_out_date, bookings.check_in_date))` uses the MySQL-only `DATEDIFF`. The test suite runs on SQLite, so this endpoint throws and has **zero test coverage** — including no authorization test. Not a vulnerability, but the most privileged read endpoint in the application is the one nothing verifies.

**Remediation:** compute nights in PHP from the loaded dates, or use a portable expression, so the endpoint can be covered by the authorization matrix recommended in F-10.

---

## 3. Controls verified as sound

Confirmed working, so they can be excluded from remediation and cited as strengths:

| Area | Result |
|---|---|
| **SQL injection** | None. All 10 `selectRaw` calls use static strings; zero `whereRaw`/`DB::statement`/`DB::select`; no user input reaches raw SQL. Eloquent parameter binding throughout. |
| **XSS** | No sinks. Zero occurrences of `v-html`, `innerHTML`, `outerHTML`, `document.write`, `eval`, or `new Function` in `resources/`. Vue's default interpolation escapes everything. |
| **Command injection / deserialization** | No `exec`, `shell_exec`, `system`, `passthru`, `proc_open`, or `unserialize` anywhere in `app/`. `session.serialization` is `json`, so APP_KEY leakage cannot become a gadget chain. |
| **CSRF** | Laravel's `web` group token validation is active on every state-changing route (the API lives in `web.php`, not a stateless API group). `XSRF-TOKEN` cookie issued; axios echoes it same-origin; `SameSite=Lax` provides a second layer. Verified the cookie is set on `/`. |
| **Password storage** | bcrypt via the `hashed` cast, `BCRYPT_ROUNDS=12`. `Password::defaults()` on register/reset/admin-create. `password` and `remember_token` in `$hidden`. |
| **Primary login brute force** | Correctly limited — locks out at attempt 6, keyed on email+IP, with a `Lockout` event. (The gap is the *second* path, F-01.) |
| **Password reset tokens** | Framework broker: hashed, single-use, 60-minute expiry, 60-second per-email throttle. `NewPasswordController` rotates `remember_token` on reset. |
| **Session handling** | `HttpOnly` set, `SameSite=Lax`, DB-backed, regenerated on login and logout, invalidated on logout and account deletion. |
| **Mass assignment** | No exploitable path. Every controller passes validator output, not `$request->all()`. Role changes require `admin` (verified `403` for a visitor). |
| **IDOR — hotel bookings** | `BookingController::show/update` enforce `user_id` ownership or `hotel_manager` (`403` otherwise). |
| **IDOR — ferry tickets** | `FerryTicketService::issue()` requires `$booking->user_id === $ticketOwnerId`, so a visitor cannot ticket against another party's booking even with a guessed `booking_id`. |
| **Concurrency / race conditions** | `lockForUpdate()` correctly applied in `HotelBookingService::create`, `FerryTicketService::issue`, `ThemeParkBookingService::book`, `sellTicket`, and both cancel paths; all wrapped in `DB::transaction`. The bugs found (F-04) are missing state guards, not lock failures. |
| **Secret management** | `.env` git-ignored and never committed (verified across all history); only `.env.example` tracked; no keys, tokens or credentials found in the repo. |
| **Input validation coverage** | Every controller action validates; enums constrained with `in:`; capacity derived server-side from the grid rather than trusted (`FerryController::storeFerry:57`); `Ferry::normaliseGrid` rejects malformed layouts; map coordinates bounded. |
| **Pagination limits** | `HotelController::index` caps `per_page` at 100 against a hostile value. |
| **File upload validation** | `image` + `mimes` allowlist + 5 MB cap; SVG excluded; spatie generates storage paths. (Residual hardening in F-21.) |

---

## 4. Prioritized remediation roadmap

**Immediately (before any demo or deployment) — breaks authentication or money:**

1. **F-01** Throttle `api/guest/login`, restrict it to `visitor` targets — *~15 lines*
2. **F-02** Remove `confirmed` from visitor-writable statuses — *~5 lines*
3. **F-05** Add the booking state machine — *~10 lines*
4. **F-04** Guard cancelled/used in park cancellation + clamp the counter — *~8 lines*
5. **F-09** Reject cancelled park tickets at validation — *~4 lines*

**This sprint — enables or amplifies the above:**

6. **F-07** Global + per-group rate limiters
7. **F-06** Throttle-before-provision, prune abandoned guests
8. **F-08** Server-side schedule/date scoping on ticket validation
9. **F-11** `composer update` / `npm audit fix`, lift the axios ceiling, add SCA to CI
10. **F-03** Opaque reference codes (needs a migration + scanner change — plan it, don't rush it)

**Before production:**

11. **F-14** Config defaults + deployment checklist + debug-mode assertion
12. **F-12** Security-headers middleware + HSTS at the edge
13. **F-13** Uniform password-reset response
14. **F-15** `MustVerifyEmail` + `verified` middleware
15. **F-19** Audit fields and an `audit` log channel

**Structural (highest long-term value):**

16. **F-10** Route-level `role:`/`can:` middleware, named permissions, complete policy set, **and a negative-authorization test matrix**. This is the change that would have caught F-01 automatically, and the one most worth writing up in the coursework.
17. **F-16, F-17, F-18, F-20, F-21, F-22, F-23** — small, independent hardening items.

---

## 5. Scope, method, and limitations

**Reviewed in full:** all 14 API controllers, 8 auth controllers, `ProfileController`, `AutoLoginGuest`, 2 form requests, 6 policies, 5 services, all 15 models, all 29 migrations, 3 seeders, all route files, `bootstrap/app.php`, `config/{app,auth,session,filesystems,media-library,permission}.php`, `compose.yaml`, `.env` / `.env.example`, `.gitignore`, git history for secret leakage, and the Vue SPA (105 files) for XSS sinks, client-side trust boundaries, token handling and storage of sensitive data.

**Dynamic testing:** 19 proof-of-concept probes executed against the full application stack via PHPUnit feature tests (real middleware, real routing, real authorization, SQLite in-memory DB). Appendix A. All probe files were removed after the run; no application code was modified by this audit.

**Not covered:**

* No testing against a deployed instance — TLS configuration, web-server hardening, CDN/WAF, container runtime and DB privileges at the deployment target are out of scope.
* SCA is limited to `composer audit` / `npm audit` advisory data as of 2026-08-04; no manual review of dependency source.
* No load or stress testing to quantify the DoS ceilings in F-06/F-07.
* Race conditions were reviewed statically (locking looks correct) but not fuzzed with concurrent load; a parallel-request harness against `FerryTicketService::issue` and `ThemeParkBookingService::book` is the recommended next step.
* `AdminController::stats` uses MySQL-only `DATEDIFF` and errors under SQLite (observed as the `500` in F-01's probe). Not a security issue, but it means that endpoint is untestable in the current test configuration — worth fixing for testability.

---

## Appendix A — Reproducer suite

The probes below were run as `tests/Feature/ZzSecurityAuditProbe{,2,3}Test.php` and then deleted. To reproduce, recreate them and run `php artisan test --filter=ZzSecurityAuditProbe`. Each writes its evidence to STDERR and asserts nothing, so probes are observational rather than pass/fail.

| Probe | Finding | Observed |
|---|---|---|
| 1, 9 | F-06 | 10 cookieless POSTs → 10 permanent guest users |
| 2 | F-01 | `/login` locks at attempt 6; `/api/guest/login` 30 attempts → no lockout; correct password → HTTP 200 |
| 3 | F-02 | `pending` → self-`PATCH` `confirmed` → HTTP 200 → ferry ticket HTTP 201 |
| 4 | F-08 | 10-day-old ticket from another sailing → HTTP 200, `status=used` |
| 5 | F-04 | capacity `[10,12,14,16,18]` against a real capacity of 10 |
| 6 | F-09 | cancelled park booking → HTTP 200, `status=used` |
| 7 | F-03 | `VFN-B0001, VFN-B0002, VFN-B0003`; party lookup on guessed id → HTTP 200 |
| 8, 11 | F-07 | 60 API requests → all 200; 25 registrations → all 201 |
| 10 | F-13 | known email 200 vs unknown email 422 with distinguishing message |
| 12 | F-22 | extra fields ignored; non-admin role change → 403 (not exploitable) |
| 13 | F-17 | `ACAO: *`, no `ACAC` |
| 14 | F-12 | all six headers MISSING; session cookie without `Secure` |
| 15, 16 | F-01 | guest→visitor and guest→**admin** account takeover via password guess |
| 17 | F-05 | 2 active bookings on one room for the same dates |
| 18 | F-15 | `MustVerifyEmail=false`, no notification sent, unverified account books successfully |
| 19 | F-16 | 25 wrong password confirmations → 25×422, no lockout |

The exact probe source is reproducible from the finding descriptions; each probe uses only model factories, `actingAs()`, and JSON HTTP calls against the routes named in the corresponding finding.

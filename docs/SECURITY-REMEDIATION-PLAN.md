# TPMS — Security Remediation Plan

**Companion to:** `docs/SECURITY-AUDIT-2026-08-04.md` (25 findings)
**Baseline:** commit `365521e`, test suite green at **211 passed / 482 assertions**
**Written:** 2026-08-04

This plan is the execution counterpart to the audit. Every task below names the
exact files, the exact change, the blast radius established by follow-up analysis,
and the acceptance test that proves it. Tasks are written to be picked up
independently — by a teammate or by another agent session — without re-deriving
context.

---

## 1. What follow-up analysis changed

Four things came out of tracing coupling that materially change the plan. Read these before sequencing anything.

### 1.1 F-02's exploit *is* the payment flow — it cannot simply be removed

`PATCH /api/bookings/{id} {status:'confirmed'}` is not a stray endpoint. It is how the visitor pays:

```
BookingConfirmationView.pay()          →  hotelStore.confirmBookings(ids)
PaymentsDueMenu (pay selected pending) →  hotelStore.confirmBooking(id)
                                       →  PATCH /api/bookings/{id} {status:'confirmed'}
```

`BookingConfirmationView.vue:44-47` even documents it: *"Payment gateway integration is out of scope for now — this simply marks every booking in the group confirmed once the mock card form is filled in."*

So the fix is **not** "delete `confirmed` from the rule set" — that breaks checkout. It is "move the transition behind a server-owned action that records a payment." That promotes F-02 from a 5-line edit to a small feature (Task 2.1). The audit's original one-line remediation was correct in principle and wrong in cost; this plan supersedes it.

**Good news:** no existing test asserts that a *visitor* can PATCH to `confirmed`. `BookingControllerTest` covers visitor→`cancelled` (line 234), cancel-frees-room (line 245), and cannot-cancel-others (line 260) only. The `hotel_manager` confirm path is separately verified to work and must keep working. **The backend change is test-safe; the frontend rewiring is the actual work.**

### 1.2 F-03's blast radius is far smaller than it looks

`reference_code` appears in 20 places across the SPA — but 18 of them only *render* it. Exactly two functions *parse* it back into a primary key:

| File | Function | Behaviour |
|---|---|---|
| `Pages/Ferry/TicketValidationView.vue:115-122` | `parseScan()` | regex → `parseInt()` → id |
| `Pages/ThemePark/TicketValidationView.vue:19-22` | `parseBookingId()` | regex → `parseInt()` → id |

If the new opaque code keeps the **same attribute name** (`reference_code`), all 18 display sites need zero changes. The migration touches 3 models, 3 tables, and 2 scanner functions. That makes F-03 a Phase-5 task rather than an epic.

### 1.3 F-24's fix must add a management scope — and repairs an existing bug

`stores/themepark.js:41` — the staff `EventManagementView` and the visitor home page call the **same** `GET /api/themepark/events`, which filters `is_active`. So toggling an event inactive already removes it from the management list with no way back. Adding naive `is_active` filters to hotels would reproduce that bug there. Every filter added in Task 4.1 must ship with a role-gated `?all=1` scope, following the precedent `FerryController::ferries()` already sets.

### 1.4 The three root causes

Fourteen of the twenty-five findings collapse into three causes. Fixing the causes is what stops the next fourteen.

| Root cause | Findings | The structural fix |
|---|---|---|
| **Duplicated security logic** — the same rule implemented in more than one place, so one copy drifts | F-01 (2nd credential path), F-04/F-09 (guard present in ferry, absent in park), F-24 (`is_active` honoured in 3 endpoints, ignored in 4) | One owner per rule: one auth action, one state machine, one query scope |
| **Client-side enforcement** — the server trusts a decision the SPA made | F-02 (payment), F-05 (transitions), F-08 (`scheduleMismatch` computed) | Server owns every state transition; the SPA becomes UX only |
| **No coarse gate** — 85 routes behind bare `auth`, authorization living in 24 inline `hasAnyRole` string checks | F-10, and the reason F-01 was invisible | Route-level `role:`/`can:` middleware + a negative-authz test matrix |

**The coursework thesis:** every single high-severity finding is a *missing guard on a
duplicated rule*, not an exotic vulnerability. F-04 and F-09 are the proof — the
ferry cancel path checks `cancelled`, the park path doesn't; same logic, two
implementations, one of them wrong.

---

## 2. Phase plan

Seven phases. Each is independently shippable and leaves the suite green.
Sizes are for a student team: **S** ≈ half a day, **M** ≈ 1 day, **L** ≈ 2–3 days.

```
Phase 0  Regression harness ──────────────► gate: 25 new tests RED
   │
   ├─ Phase 1  Stop the bleeding (F-01,04,05,08,09,11)   ► gate: those tests GREEN
   │      │
   │      ├─ Phase 2  Payment integrity (F-02)            ► needs 1.2's state machine
   │      ├─ Phase 3  Abuse resistance (F-06,07,16)       ║ parallel with 2
   │      └─ Phase 4  Exposure + config (F-12,13,14,17,18,24,25)  ║ parallel with 2,3
   │
   ├─ Phase 5  Identity artefacts (F-03)                  ► needs Phase 0 only
   └─ Phase 6  Structural authz (F-10) + tail (F-15,19,20,21,22,23)
```

Phases 2, 3 and 4 are mutually independent — split them across the team.
Phase 6 lands last because it touches every route file and would conflict with everything else.

---

### PHASE 0 — Regression harness · **S** · blocks everything

Convert the 25 audit probes into permanent tests **that fail today**. This is the highest-leverage half-day in the plan: it turns every finding into a red bar that goes green when fixed, and it is what makes the rest of the work verifiable rather than hopeful.

**Create** `tests/Feature/Security/` with:

| File | Asserts (currently failing) |
|---|---|
| `AuthenticationHardeningTest.php` | `api/guest/login` locks out ≤6 attempts; rejects non-visitor targets; `/confirm-password` throttles |
| `BookingIntegrityTest.php` | visitor cannot PATCH→`confirmed`; `cancelled` is terminal; paying requires a `Payment` row |
| `TicketValidationTest.php` | wrong-sailing ticket rejected; past-date rejected; cancelled park ticket rejected |
| `CapacityIntegrityTest.php` | repeat cancel is idempotent; `available_capacity` never exceeds `capacity_per_slot` |
| `RateLimitTest.php` | booking/checkout/register/api groups return 429 under load |
| `PublicExposureTest.php` | inactive hotel/ferry/event absent from every anonymous endpoint |
| `AuthorizationMatrixTest.php` | **see below** |

The matrix is the keystone. Data-drive it so adding a route means adding one row:

```php
public static function privilegedRoutes(): array
{
    // [method, uri, allowed roles]
    return [
        ['GET',    '/api/admin/stats',            ['admin']],
        ['GET',    '/api/admin/users',            ['admin']],
        ['POST',   '/api/admin/users',            ['admin']],
        ['GET',    '/api/map/locations/manage',   ['admin']],
        ['POST',   '/api/hotels',                 ['hotel_manager', 'admin']],
        ['POST',   '/api/ferries',                ['ferry_operator', 'admin']],
        ['GET',    '/api/ferry/schedule-templates', ['ferry_operator', 'admin']],
        ['POST',   '/api/themepark/tickets/sell', ['themepark_staff', 'admin']],
        ['GET',    '/api/themepark/reports/sales',['themepark_staff', 'admin']],
        // ... one row per privileged route
    ];
}

#[DataProvider('privilegedRoutes')]
public function test_every_role_is_correctly_gated(string $method, string $uri, array $allowed): void
{
    foreach (['visitor', 'hotel_manager', 'ferry_operator', 'themepark_staff', 'admin'] as $role) {
        $user = User::factory()->create();
        $user->assignRole($role);

        $status = $this->actingAs($user)->json($method, $uri)->status();

        in_array($role, $allowed, true)
            ? $this->assertNotSame(403, $status, "{$role} should reach {$method} {$uri}")
            : $this->assertSame(403, $status, "{$role} must NOT reach {$method} {$uri}");
    }

    $this->assertContains($this->json($method, $uri)->status(), [401, 302],
        "anonymous must not reach {$method} {$uri}");
}
```

**Acceptance:** ~25 new failing tests; the existing 211 still pass. Commit the red suite — the diff that turns it green is the deliverable for Phases 1–4.

> Note: `/api/admin/stats` cannot enter the matrix until F-25 is fixed (Task 4.5), because it 500s on SQLite. Do 4.5 first if you want full coverage from day one.

---

### PHASE 1 — Stop the bleeding · **M** · do first

Six surgical changes. No schema, no frontend, no new endpoints.

#### Task 1.0 — Patch vulnerable dependencies (F-11) · S
Do this while the baseline is green so any breakage is unambiguous.
```bash
composer update --with-all-dependencies && composer audit
npm audit fix
```
Then lift the axios ceiling in `package.json` — `"axios": ">=1.11.0 <=1.14.0"` actively pins it *below* the fix for the XSRF-token cross-origin leak (GHSA-xx6v-rp6x-q39c), which is directly relevant to this app's cookie-CSRF scheme:
```json
"axios": "^1.19.0"
```
Run `npm run build` and the full PHP suite. **Acceptance:** `composer audit` and `npm audit --omit=dev` both clean; 211 tests still pass.

#### Task 1.1 — Close the second credential path (F-01, CRITICAL) · S
`routes/web.php` + `GuestController::login()`. Full code in audit §F-01. Three parts:
1. `->middleware('throttle:5,1')` on the route.
2. A `RateLimiter` keyed on email+IP inside the method, mirroring `LoginRequest::throttleKey()`.
3. `abort(403)` unless `$target->hasRole('visitor')` — a cart merge never targets a staff account.

Accepted UX trade-off: a staff member using guest checkout must log in via `/login`. That is correct; document it in the method's docblock.

**Better still, if time allows:** delete the hand-rolled `Hash::check` and call `Auth::attempt()` through a shared throttled action, so the codebase has exactly one credential-verification path. That removes the root cause (duplication) rather than patching the copy.

**Acceptance:** `AuthenticationHardeningTest` green; `GuestCheckoutTest` (12 existing assertions) still green.

#### Task 1.2 — Booking state machine (F-05) · S
`BookingController::update()`. Replace target-state assignment with an explicit transition table (code in audit §F-05). Add `TRANSITIONS` as a private const; `cancelled` is terminal.

Also split the rule set by role so a visitor can only cancel — this is the *authorization* half of F-02 and it is safe to land now, **provided Task 2.1 ships in the same release**, or the visitor pay button breaks:

```php
$isStaff = $user->hasRole('hotel_manager');
$validated = $request->validate([
    'status' => ['required', $isStaff ? 'in:confirmed,cancelled' : 'in:cancelled'],
]);
```

> **Sequencing warning.** 1.2 and 2.1 are one release. If you must land 1.2 alone, keep `confirmed` visitor-writable for now and take only the transition table — that still kills F-05 (the double-book) without breaking checkout.

**Acceptance:** `BookingIntegrityTest` transition cases green; `test_owner_can_cancel_their_booking` and `test_cancelling_a_booking_frees_the_room` still green.

#### Task 1.3 — Park capacity guards (F-04) · S
`ThemeParkController::cancelBooking()`. Early-return on `cancelled`, reject `used`, and clamp the restore to `capacity_per_slot` (code in audit §F-04).

While here, audit every counter restore for the same omission — this is the duplication pattern from §1.4. `FerryController::cancelTicket()` is the correct reference implementation.

**Acceptance:** `CapacityIntegrityTest` green — 5 repeat cancels leave capacity at 10, not 18.

#### Task 1.4 — Park ticket validation guards (F-09) · S
`ThemeParkTicketController::validateTicket()`. Reject `cancelled`; reject slots whose `status !== 'scheduled'` (code in audit §F-09).

#### Task 1.5 — Server-side sailing scope on ferry validation (F-08) · S
`FerryController::validateTicket()` — require `schedule_id` in the request, reject mismatches, reject non-`scheduled` and non-today departures (code in audit §F-08). Then have `ferryStore.validateTicketOnSite()` send the selected `schedule_id`, and keep the `scheduleMismatch` computed as UX only.

Touches: `FerryController.php`, `stores/ferry.js`, `Pages/Ferry/TicketValidationView.vue:219`.

**Phase 1 gate:** all of `AuthenticationHardeningTest`, `CapacityIntegrityTest`, `TicketValidationTest` green. Every High and the Critical are closed except F-02 and F-03.

---

### PHASE 2 — Payment integrity · **M** · the real F-02 fix

The one place this plan adds a feature rather than a guard. Worth doing properly: it is the most defensible thing in the coursework writeup, because it converts a client-side assertion into a server-owned, auditable record.

#### Task 2.1 — `payments` table + server-owned settle action · M

**Migration:**
```php
Schema::create('payments', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->morphs('payable');                    // Booking, FerryTicket, EventBooking
    $table->decimal('amount', 10, 2);
    $table->string('method');                     // card | cash
    $table->string('status')->default('captured');
    $table->string('reference')->unique();        // server-generated, never from the client
    $table->foreignId('recorded_by')->nullable()->constrained('users');  // staff, for cash
    $table->timestamps();
});
```

**Service** — `HotelBookingService::settle(Collection $bookings, User $actor, string $method)`:
one owner for the `pending → confirmed` transition. It must:
* re-read each booking `lockForUpdate()` inside the transaction;
* verify `status === 'pending'` (reject otherwise with the reference code in the message);
* take the amount from **`$booking->total_price`**, never from the request;
* create one `Payment` per booking and flip status, atomically.

**Endpoint** — `POST /api/bookings/pay` (inside the `auth` group), accepting `booking_ids[]` because that is how the UI actually behaves (pay a group at once). Full handler sketched in the audit; the ownership check is `abort(403)` per booking.

**Also route `CartController::checkout()` through `settle()`.** Today it calls `hotelBookings->confirm()` directly, so the cart path confirms with no payment record — the same gap by a different door. One confirm path, or the drift returns.

#### Task 2.2 — Rewire the SPA · S
* `stores/hotel.js:198` — `confirmBooking`/`confirmBookings` call `POST /api/bookings/pay` with `{booking_ids}`.
* Keep a separate `staffConfirmBooking` on the old PATCH for `HotelDashboardView` (front-desk cash-received). Two callers, two intents, two methods — do not share one.
* `BookingConfirmationView.vue:47` and `PaymentsDueMenu` need no logic change if the store's signature holds.
* Replace the stale comment at `BookingConfirmationView.vue:44` — payment is now server-recorded.

**Acceptance:** visitor pay flow works end to end; a `Payment` row exists per confirmed booking; `PATCH {status:'confirmed'}` as a visitor returns 422; manager PATCH still 200. `CartCheckoutTest` still green.

---

### PHASE 3 — Abuse resistance · **M** · parallel with 2

#### Task 3.1 — Rate limiting (F-07) · S
Named limiters in `AppServiceProvider::boot()` + `$middleware->throttleApi()` in `bootstrap/app.php` (code in audit §F-07). Apply `throttle:auth` to `login`, `register`, `forgot-password`, `reset-password`, `confirm-password`, `api/guest/login`; `throttle:writes` to the booking/checkout group.

> Watch out: `RateLimitTest` needs `CACHE_STORE=array` (already set in `phpunit.xml`) and must clear the limiter between tests, or later tests inherit a tripped limiter. Use `RateLimiter::clear()` in `tearDown`, or `withoutMiddleware(ThrottleRequests::class)` in the suites that hammer endpoints.

#### Task 3.2 — Guest lifecycle (F-06) · S
Three parts, in order of value:
1. **Throttle before provisioning** — middleware order matters:
   ```php
   Route::middleware(['throttle:10,1', AutoLoginGuest::class])->prefix('api')->group(...);
   ```
2. **Validate before provisioning** — move guest creation out of middleware into the controllers (or a `FormRequest`-aware middleware) so a malformed body no longer writes a `users` row. This is the actual fix; the throttle only caps the rate.
3. **Prune** — scheduled cleanup of guests older than 7 days with no bookings (code in audit §F-06). Add to `routes/console.php` beside the existing `schedules:generate`.

#### Task 3.3 — Throttle `/confirm-password` (F-16) · XS
`->middleware('throttle:6,1')` in `routes/auth.php:40`.

---

### PHASE 4 — Exposure and configuration · **M** · parallel with 2, 3

#### Task 4.1 — Consistent `is_active` enforcement (F-24) · M
The largest task in this phase because of the management-scope requirement (§1.4).
1. Add a `scopeVisibleTo(?User, bool $wantsAll)` scope (code in audit §F-24) — **one owner for the rule.**
2. Apply to `HotelController::index`/`show`; constrain the `ferry` eager-load in `FerryController::schedules()`; reject inactive in `ThemeParkController::show()` for non-staff.
3. Move `themepark/slots` out of the public group into `auth` + staff role — its own docblock says it is a staff calendar endpoint.
4. Update `fetchHotels`, `fetchEvents`, `fetchAllSlots` in the Pinia stores to pass the management flag on staff screens. **This also repairs the one-way `is_active` toggle bug** in `EventManagementView`.

**Acceptance:** `PublicExposureTest` green; staff can still see and re-activate inactive records (regression-test that explicitly — it is broken today).

#### Task 4.2 — Security headers (F-12) · S
`SecurityHeaders` middleware appended to the `web` group (code in audit §F-12). Two gotchas the CSP must accommodate: the inline theme script at `app.blade.php:16-22` (prefer a nonce over `'unsafe-inline'` for `script-src`) and Leaflet's inline styles + tile hosts in `img-src`. Verify the map, QR **camera** scanner (`camera=(self)` in `Permissions-Policy`) and charts all still work.

#### Task 4.3 — Config hardening (F-14) · S
`.env.example`: `APP_DEBUG=false`, `LOG_LEVEL=warning`, `SESSION_SECURE_COOKIE=true`, `SESSION_ENCRYPT=true`, non-root `DB_USERNAME`. `compose.yaml`: bind MySQL to `127.0.0.1:${FORWARD_DB_PORT}:3306`, drop `MYSQL_ALLOW_EMPTY_PASSWORD`. Add the production assertion to `AppServiceProvider::boot()` and a deployment checklist to `README.md`.

#### Task 4.4 — Uniform reset response + CORS (F-13, F-17) · S
`PasswordResetLinkController::store()` returns one message either way. Publish `config/cors.php` pinned to `APP_URL`.

#### Task 4.5 — Portable stats query (F-25) · S
Replace `DATEDIFF` in `AdminController::stats():126,156` so the endpoint runs on SQLite, then add it to the Phase-0 authorization matrix. Do this **before** Phase 0 if you want the matrix complete from the start.

#### Task 4.6 — PII minimisation (F-18) · S
Replace `with('user')` + raw model serialization with `with('user:id,name')` or purpose-scoped Resources. Follow the existing `HotelResource`/`RoomResource` pattern. Sites: `BookingController::index`, `FerryController::showTicket`/`passengers`/`partyStatus`, `ThemeParkTicketController::showTicket`.

---

### PHASE 5 — Identity artefacts (F-03) · **M** · independent

Scoped by §1.2 to 3 models + 3 migrations + 2 scanner functions.

#### Task 5.1 — Opaque reference codes · M
1. **Migration** per table (`bookings`, `ferry_tickets`, `event_bookings`): add `reference_code` as nullable+unique, backfill existing rows with generated codes, then set non-nullable. Keep the `VFN-B`/`VFN-T`/`VFN-E` prefix — the letter is load-bearing for scan routing and is not a secret.
   ```php
   'VFN-T'.Str::upper(Str::random(12))
   ```
2. **Models** — delete the `referenceCode()` accessor and `$appends`; add a `creating` hook. **Keep the attribute name `reference_code`** so all 18 display sites are untouched.
3. **Lookup endpoints** — `GET /api/ferry/tickets/lookup?code=` and the park equivalent, resolving `where('reference_code', $code)`. Keep them role-gated exactly as the current `showTicket` is.
4. **Scanners** — `parseScan()` / `parseBookingId()` keep prefix-based type detection but stop extracting an id; they pass the raw code to the lookup endpoint. Retain manual entry.

**Acceptance:** codes are unguessable and non-sequential; scanning a valid QR still validates; a forged sequential code (`VFN-T0001`) 404s. Existing `FerryTicketTest`/`ThemeParkTicketTest` may assert on codes — update rather than delete those assertions.

> Migration risk: this is the only task with a data migration. Test the backfill on a seeded copy first (`php artisan migrate:fresh --seed` then the migration) and confirm no duplicate-key failures on the unique index.

---

### PHASE 6 — Structural authorization + tail · **L** · last

Land after everything else — it touches every route file and would conflict with Phases 1–5.

#### Task 6.1 — Route-level gates and named permissions (F-10) · L
1. Wrap route groups in `role:` middleware so the coarse boundary is declarative and cannot be omitted (code in audit §F-10).
2. Replace role-name string literals with named permissions (`tickets.validate`, `fleet.manage`, `users.manage`, `promotions.manage`) seeded onto roles in `RoleSeeder`; check `$user->can(...)`. A role rename then touches one file instead of 24 call sites.
3. Complete the policy set — `Booking`, `EventBooking`, `FerryTicket`, `MapLocation`, `Promotion`, `User` — and delete the 24 inline `hasAnyRole` checks in favour of `Gate::authorize`.
4. The Phase-0 matrix is the regression net: **it must stay green through every step.** That is the whole point of building it first.

#### Task 6.2 — Tail hardening · M
| Finding | Change |
|---|---|
| F-15 | `User implements MustVerifyEmail`; `verified` middleware on transactional routes; verify at `GuestController::claim` so guest checkout still works |
| F-19 | `validated_by`/`validated_at`/`cancelled_by` columns; an `audit` log channel; log `Auth::Failed` and `Lockout` events |
| F-20 | `$request->session()->regenerate()` in `GuestController::claim()` |
| F-21 | Re-encode/strip metadata on upload via a spatie conversion (needs F-12's `nosniff`) |
| F-22 | Remove `is_guest` from `User`'s `#[Fillable]`; set it via `forceFill` |
| F-23 | TOTP for `admin`/staff; remove unused `laravel/sanctum` |

---

## 3. Suggested sequencing

| Slot | Work | Findings closed |
|---|---|---|
| 1 | Phase 0 + Task 4.5 | — (harness) |
| 2 | Phase 1 | F-01, F-04, F-05, F-08, F-09, F-11 |
| 3 | Phase 2 ‖ Phase 3 ‖ Phase 4 | F-02, F-06, F-07, F-12, F-13, F-14, F-16, F-17, F-18, F-24, F-25 |
| 4 | Phase 5 | F-03 |
| 5 | Phase 6 | F-10, F-15, F-19, F-20, F-21, F-22, F-23 |

**Minimum viable hardening**, if the timeline collapses: Phase 0 + Phase 1 + Tasks 2.1/2.2 + 3.1. That closes the Critical and all five Highs, and is defensible as "we found, proved, and fixed every severe issue, and left a regression suite that stops them recurring."

---

## 4. Definition of done

A phase is done when:

1. Its Phase-0 tests are green and the original 211 still pass.
2. `composer audit` and `npm audit --omit=dev` are clean (from Task 1.0 onward).
3. The `AuthorizationMatrixTest` is green and includes any route the phase added.
4. No new inline `hasAnyRole` check was introduced — new authorization goes through a policy or route middleware.
5. `git diff` contains no change to a security control without a corresponding test.

**Suggested CI gate** (`.github/workflows/ci.yml`): `php artisan test` + `composer audit` + `npm audit --omit=dev`, failing the build on any of the three. Without this, F-11 silently returns within a month.

---

## 5. What this plan deliberately does not do

| Not doing | Why |
|---|---|
| Real payment gateway | Out of scope for the course. Task 2.1 makes the *boundary* correct — server-owned transition, recorded amount, audit trail — so a gateway later replaces one service method. |
| Rewrite `Ferry::deck()` / layout system | Reviewed and found sound: `normaliseGrid` rejects malformed input, capacity is derived server-side and never trusted from the client. No change needed. |
| Add MFA in the first four phases | Correct to want (F-23), but it is dependent on F-01 being fixed to matter, and it is a UX-heavy addition. Phase 6. |
| Encrypt data at rest / field-level encryption | No payment or identity data is stored once Task 2.1 keeps card data out (it already never reaches the server). Not justified here. |
| Change the guest-checkout UX | The pattern is legitimate and good product design. F-06 fixes its *implementation* (throttle, validate-then-provision, prune), not the concept. |
| Concurrency fuzzing | Locking was reviewed and is correct; the bugs found were missing state guards, not lock failures. Worth doing eventually (audit §5) but no finding depends on it. |

---

## 6. Coursework writeup angles

The strongest material here is not the vulnerability list — it is the pattern:

1. **Duplicated rules diverge.** F-04 vs. the correct `FerryController::cancelTicket`, and F-09 vs. the correct ferry validation, are the same logic implemented twice with one copy missing a guard. F-01 is a second credential path. F-24 is one rule in seven endpoints, honoured in three. Argue for single-ownership of security rules, and use these as evidence.
2. **A control in the client is not a control.** F-08 is the cleanest example in the codebase — the developer wrote a `scheduleMismatch` computed and a comment saying *"this is what actually stops it"*, and the server accepted a 10-day-old ticket from another sailing. Contrast the two-line server-side fix.
3. **The gate that was bypassable through the back door.** F-24's `?all=1` → 403 works exactly as designed, and the same data leaves through `GET /api/ferry/schedules`. A good diagram: one asset, seven doors, three locked.
4. **Testing as a security control.** The authorization matrix would have caught F-01 the day it was written. Quantify it: 25 exploits, ~25 tests, half a day.
5. **Severity ≠ effort.** F-01 is Critical and ~15 lines. F-02 is High and needs a new table, service method, endpoint and frontend rewiring — because the vulnerability *was* the feature. Good material on why triage needs both axes.

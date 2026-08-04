# TPMS Security Remediation — Implementation Handoff

> ## ✅ IMPLEMENTED — 2026-08-04
>
> All 25 findings are closed. **310 tests pass (747 assertions)**, up from a 211-test
> baseline; `composer audit` and `npm audit --omit=dev` are both clean; `npm run build`
> succeeds.
>
> This document is retained as the specification and rationale record. The task cards
> below describe what was done and why — a few deviated from the plan during
> implementation, and each of those is noted inline with **⚠ AS BUILT**.
>
> Regression tests live in `tests/Feature/Security/`. Start with
> `AuthorizationMatrixTest` (17 privileged routes × 5 roles + anonymous) and add a row
> whenever you add a privileged route.

**Audience:** an implementing agent or developer with **no prior context** on this work.
**This document is self-contained. Read it and only it to implement.**

| Companion doc | Use it for |
|---|---|
| `docs/SECURITY-AUDIT-2026-08-04.md` | Evidence for each finding (verified exploit output). Read a finding only if you need to understand *why* a change is required. |
| `docs/SECURITY-REMEDIATION-PLAN.md` | Strategy, root-cause analysis, coursework framing. Not needed to implement. |

**Baseline:** commit `365521e` · test suite **211 passed / 482 assertions** · all findings verified by executed exploit, not inference.

---

## PART 0 — READ FIRST

### 0.1 Environment facts (verified, do not re-derive)

| Fact | Value |
|---|---|
| Stack | Laravel **13.4.0**, PHP 8.3, Vue 3 SPA + Pinia + vue-router, Vite 8 |
| **`php` is NOT on the bash PATH** | Use the **PowerShell** tool for all `php`/`composer`/`npm` commands. The Bash tool works for `git`/`ls`/`grep` only. |
| Run tests | `php artisan test` · filtered: `php artisan test --filter=ClassName` |
| Test DB | SQLite **in-memory** (`phpunit.xml`), `CACHE_STORE=array`, `SESSION_DRIVER=array`, `BCRYPT_ROUNDS=4` |
| Roles (exact strings) | `visitor`, `hotel_manager`, `ferry_operator`, `themepark_staff`, `admin` |
| Role assignment idiom | `User::factory()->create()->assignRole('visitor')` — `assignRole()` returns the model, chaining is used throughout the existing suite |
| All routes live in | `routes/web.php` (**not** `routes/api.php` — there is none). The API is session/cookie authenticated inside the `web` middleware group, so **CSRF applies**. |
| Permissions package | `spatie/laravel-permission` **8.3.0** — only *roles* are used; no permission is defined anywhere yet |

### 0.2 Factories — all 12 exist and are usable

Verified defaults you must know to write correct tests:

| Factory | Notable defaults |
|---|---|
| `UserFactory` | `email_verified_at => now()`, password = `'password'` |
| `BookingFactory` | `status => 'pending'`; auto-creates `user_id`, `room_id` |
| `FerryScheduleFactory` | `available_seats => 40`, `status => 'scheduled'`, auto-creates `ferry_id` |
| `EventSlotFactory` | `available_capacity => 20`, `status => 'scheduled'`, auto-creates `event_id` |
| `EventBookingFactory` | `status => 'confirmed'`, **`user_id` always set** — pass `user_id => null` explicitly for walk-in tests |
| `FerryTicketFactory` | `status => 'issued'`, `booking_id => Booking::factory(['status' => 'confirmed'])` |

> `UserFactory` setting `email_verified_at => now()` means Task 6.2's `verified` middleware will **not** break the existing suite. Confirmed.
>
> For capacity tests, set **both** `capacity_per_slot` (on the event) and `available_capacity` (on the slot) explicitly — the factory defaults are inconsistent with each other (20 vs. whatever the event says).

### 0.3 ⚠ BLOCKER — resolve before Task 4.1 or 6.1

**spatie's middleware aliases are NOT registered.** `bootstrap/app.php` contains no `alias()` call, and spatie 8 does not auto-register in Laravel 11+ bootstrap style. Any use of `'role:admin'` today fails with **`Target class [role] does not exist`**.

Fix once, in `bootstrap/app.php` (see **Task 0.1**), before any task that uses `role:` middleware.

### 0.4 ⚠ Concurrent modifications — DO NOT TOUCH

Another session is actively editing the working tree. As of handoff these were modified or new and are **unrelated to security work**:

```
 M resources/css/app.css
 M resources/js/Components/IslandMap.vue
 M resources/js/Components/ui/TIcon.vue
 M resources/js/Pages/Welcome.vue
?? resources/js/Components/MediaRail.vue
?? resources/js/Components/RailCard.vue
?? resources/js/composables/useReveal.js
?? resources/js/utils/icons.js
```

**Consequences for you:**
* **Never cite or rely on line numbers in `resources/js/`.** Every frontend edit below is anchored on a **unique code string** — match that, not a line number.
* Do not revert, stage, or commit those files. Commit only files you deliberately changed.
* `git stash` is unsafe here. Avoid it.

### 0.5 Ground rules

1. **One task = one commit.** Message: `security(F-xx): <what>`.
2. **The existing 211 tests must stay green after every task.** If one breaks, the fix is wrong — do not edit the existing test to make it pass unless a task explicitly says to.
3. Do not introduce new inline `hasAnyRole()` checks. New authorization goes through a policy or route middleware.
4. Never take a monetary amount, a status, or an identity from the request body when the server can derive it.
5. If a task's "current code" anchor does not match, **stop and report** — do not guess.

---

## PART 1 — TASK INDEX

| # | Task | Finding | Size | Depends on |
|---|---|---|---|---|
| **0.1** | Register spatie middleware aliases | blocker | XS | — |
| **0.2** | Make `AdminController::stats` portable | F-25 | S | — |
| **0.3** | Phase 0 regression harness (commit RED) | — | S | 0.2 |
| **1.0** | Patch vulnerable dependencies | F-11 | S | 0.3 |
| **1.1** | Close the second credential path | **F-01 CRIT** | S | 0.3 |
| **1.2** | Booking state machine | F-05 | S | 0.3 |
| **1.3** | Park capacity guards | F-04 | S | 0.3 |
| **1.4** | Park ticket validation guards | F-09 | S | 0.3 |
| **1.5** | Server-side sailing scope | F-08 | S | 0.3 |
| **2.1** | `payments` table + settle action | F-02 | M | 1.2 |
| **2.2** | Rewire SPA payment flow | F-02 | S | 2.1 |
| **3.1** | Rate limiting | F-07 | S | 0.3 |
| **3.2** | Guest lifecycle | F-06 | S | 3.1 |
| **3.3** | Throttle `/confirm-password` | F-16 | XS | 3.1 |
| **4.1** | Consistent `is_active` enforcement | F-24 | M | 0.1 |
| **4.2** | Security headers | F-12 | S | — |
| **4.3** | Config hardening | F-14 | S | — |
| **4.4** | Uniform reset response + CORS | F-13, F-17 | S | — |
| **4.6** | PII minimisation | F-18 | S | — |
| **5.1** | Opaque reference codes | F-03 | M | 0.3 |
| **6.1** | Route-level gates + named permissions | F-10 | L | all above |
| **6.2** | Tail hardening | F-15,19,20,21,22,23 | M | 6.1 |

### ⚠ AS BUILT — deviations from the plan

Six things turned out differently once implemented. Each was verified by test.

| # | Planned | As built | Why |
|---|---|---|---|
| 3.1 | `$middleware->throttleApi('api')` in `bootstrap/app.php` | Removed; limiters applied per group in `routes/web.php` | **`throttleApi()` is a no-op here.** It attaches to the `api` route group, and this app serves its whole API from `routes/web.php`. It would have looked like rate limiting while doing nothing. |
| 3.2 | Throttle-then-`AutoLoginGuest` middleware | `AutoLoginGuest` **deleted**; `App\Support\GuestSession::ensure()` called from the 3 controllers after validation | Throttling only caps the rate; the actual defect was provisioning before validation. Middleware cannot run after a controller's validation, so the logic moved into the controllers. |
| 4.2 | CSP with `'unsafe-inline'` for scripts | Per-request nonce via `Vite::useCspNonce()` | Laravel's Vite helper applies the nonce to every tag it emits, including the inline prefetch script — so a nonce was no harder than `'unsafe-inline'` and strictly better. The policy also relaxes for the Vite dev-server origin when not in production, or local dev breaks. |
| 5.1 | Prefix-based `parseScan` retained | Bare-number scans **no longer accepted** | A bare number never identified a ticket so much as guessed at one. Accepting it would have preserved the vulnerability through the manual-entry field. |
| 6.1 | Convert all 24 inline `hasAnyRole()` checks to permissions | Route middleware added + **policies** converted to named permissions; inline controller checks **retained** as a deliberate second layer | Removing them would have left authorization in exactly one place. Keeping both means the matrix test asserts the gate holds even if one layer is later edited. Noted as follow-up in Task 6.1. |
| 6.2 | F-15 gate transactional routes behind `verified` | `MustVerifyEmail` implemented and mail sent on register + guest-claim, but booking routes **not** gated | Gating booking behind `verified` would break guest checkout, which is a core product flow by design. `User::hasVerifiedEmail()` returns true for guest placeholders, whose `@guest.tpms.local` addresses are unreachable and must never be mailed. Whether to require verification before booking is a product decision, not a security one. |

**Also worth knowing:**

* **`tests/TestCase.php` now calls `Cache::flush()` in `setUp()`.** Rate-limiter state lives in the array cache, which persists for the whole PHPUnit process — without this, one test that deliberately trips a limiter throttles every test after it (trap T-2). This was necessary, not optional.
* **Three existing tests were updated, none deleted:** the ferry validate tests now pass `schedule_id` (two of them would otherwise have passed for the wrong reason — 422 from a missing param rather than the status under test); `EventSlotListTest` now uses `themepark_staff` and gained a visitor-refused case, because it had been asserting the leak in F-24; `ThemeParkEventTest`'s reference-code test now asserts an opaque format instead of `VFN-E%04d`.
* **`SeederIntegrityTest::test_migrations_are_reversible` caught a real bug in the reference-code migration's own `down()`** — a hand-written index name produced a doubled table prefix (`bookings_bookings_reference_code_unique`). Fixed by passing the column and letting Laravel derive the name.
* **Pint reformatted `MapLocationController.php` and `PromotionController.php`,** which had pre-existing style violations, when it was run over the touched files. `app/Models/MapLocation.php` still fails `pint --test` and was left alone as out of scope.

**Hard gates:** `0.1 → 0.2 → 0.3` are strictly sequential. After 0.3, Phase 1 is sequential-ish (independent files, so order is free). **Phases 2 / 3 / 4 / 5 are mutually independent** — parallelise. **6.1 last** (touches every route).

**⚠ Release coupling:** **Tasks 1.2 and 2.1+2.2 must ship together.** 1.2 removes the visitor's ability to PATCH `status: confirmed`; 2.1/2.2 provide the replacement. Landing 1.2 alone breaks the visitor pay button. If you must land 1.2 alone, apply only its transition table and keep `confirmed` visitor-writable — that still closes F-05.

---

## PART 2 — TASK CARDS

---

### TASK 0.1 — Register spatie middleware aliases · XS

**Why:** `role:` middleware is unusable until this exists (§0.3).

**File:** `bootstrap/app.php`

**Current code (match exactly):**
```php
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
```

**Replace with:**
```php
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

        // spatie/laravel-permission does not register these in Laravel 11+
        // bootstrap style; without them `role:admin` throws
        // "Target class [role] does not exist".
        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
        ]);
    })
```

Class names verified present in `vendor/spatie/laravel-permission/src/Middleware/`.

**Acceptance:** add a throwaway route `Route::get('/_alias_probe', fn () => 'ok')->middleware('role:admin');`, confirm an admin gets 200 and a visitor 403, then delete the route. `php artisan test` → 211 pass.

---

### TASK 0.2 — Make `AdminController::stats` portable · S · F-25

**Why:** the endpoint uses MySQL-only `DATEDIFF`, so it 500s on SQLite and has **zero test coverage** — including no authorization test. It cannot enter the Task 0.3 matrix until fixed.

**File:** `app/Http/Controllers/Api/AdminController.php` — two sites.

**Site 1 — `$hotelRevenue`, current code:**
```php
            ->selectRaw('SUM(rooms.price_per_night * DATEDIFF(bookings.check_out_date, bookings.check_in_date)) as total')
            ->value('total') ?? 0;
```

**Site 2 — `$hotelRevenueDaily`, current code:**
```php
            ->selectRaw('DATE(bookings.created_at) as day, SUM(rooms.price_per_night * DATEDIFF(bookings.check_out_date, bookings.check_in_date)) as aggregate')
```

**Approach.** `bookings.total_price` already stores `nights × price_per_night` (set in `HotelBookingService::create`). Sum that column instead of recomputing from dates — it removes the join, the portability problem, and a source of drift in one move:

```php
// Site 1
$hotelRevenue = DB::table('bookings')
    ->where('status', 'confirmed')
    ->sum('total_price') ?? 0;

// Site 2
$hotelRevenueDaily = DB::table('bookings')
    ->where('status', 'confirmed')
    ->where('created_at', '>=', $since)
    ->selectRaw('DATE(created_at) as day, SUM(total_price) as aggregate')
    ->groupBy('day')
    ->pluck('aggregate', 'day');
```

Remove the now-unused `->join('rooms', ...)` from both queries. `DATE()` is supported by both SQLite and MySQL — leave it.

**Acceptance:**
```powershell
php artisan test --filter=AdminController
```
Then confirm manually that an admin gets 200 from `GET /api/admin/stats` under SQLite. Add to Task 0.3's matrix.

---

### TASK 0.3 — Phase 0 regression harness · S · **commit this RED**

Create `tests/Feature/Security/`. These tests **must fail now and pass after Phases 1–5** — that is the deliverable. Commit them red with message `test(security): add failing regression suite for audit findings`.

Below are two complete, paste-ready files. Write the remaining four (`BookingIntegrityTest`, `TicketValidationTest`, `RateLimitTest`, `PublicExposureTest`) following the same shape, deriving assertions from the audit's verified probe output.

#### `tests/Feature/Security/AuthorizationMatrixTest.php` — the keystone

```php
<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * One row per privileged route. Adding a privileged route means adding a row.
 * This is the regression net for Task 6.1 - it must stay green through every
 * step of the authorization refactor.
 */
class AuthorizationMatrixTest extends TestCase
{
    use RefreshDatabase;

    private const ALL_ROLES = ['visitor', 'hotel_manager', 'ferry_operator', 'themepark_staff', 'admin'];

    /** @return array<string, array{string, string, list<string>}> */
    public static function privilegedRoutes(): array
    {
        return [
            // key                        => [method, uri, roles allowed]
            'admin stats'                 => ['GET',  '/api/admin/stats',              ['admin']],
            'admin user list'             => ['GET',  '/api/admin/users',              ['admin']],
            'admin user create'           => ['POST', '/api/admin/users',              ['admin']],
            'map manage'                  => ['GET',  '/api/map/locations/manage',     ['admin']],
            'map create'                  => ['POST', '/api/map/locations',            ['admin']],
            'hotel create'                => ['POST', '/api/hotels',                   ['hotel_manager', 'admin']],
            'ferry create'                => ['POST', '/api/ferries',                  ['ferry_operator', 'admin']],
            'ferry schedule create'       => ['POST', '/api/ferry/schedules',          ['ferry_operator', 'admin']],
            'ferry templates list'        => ['GET',  '/api/ferry/schedule-templates', ['ferry_operator', 'admin']],
            'ferry walkup ticket'         => ['POST', '/api/ferry/tickets/walkup',     ['ferry_operator', 'admin']],
            'park event create'           => ['POST', '/api/themepark/events',         ['themepark_staff', 'admin']],
            'park slot templates list'    => ['GET',  '/api/themepark/slot-templates', ['themepark_staff', 'admin']],
            'park sell ticket'            => ['POST', '/api/themepark/tickets/sell',   ['themepark_staff', 'admin']],
            'park sales report'           => ['GET',  '/api/themepark/reports/sales',  ['themepark_staff', 'admin']],
            'park capacity'               => ['GET',  '/api/themepark/capacity',       ['themepark_staff', 'admin']],
            'promotions manage'           => ['GET',  '/api/promotions/manage',
                ['hotel_manager', 'themepark_staff', 'ferry_operator', 'admin']],
        ];
    }

    #[DataProvider('privilegedRoutes')]
    public function test_route_is_gated_for_every_role(string $method, string $uri, array $allowed): void
    {
        $this->seed(RoleSeeder::class);

        foreach (self::ALL_ROLES as $role) {
            $user = User::factory()->create()->assignRole($role);

            $status = $this->actingAs($user)->json($method, $uri)->status();

            if (in_array($role, $allowed, true)) {
                $this->assertNotSame(403, $status,
                    "{$role} SHOULD reach {$method} {$uri}, got 403");
            } else {
                $this->assertSame(403, $status,
                    "{$role} MUST NOT reach {$method} {$uri}, got {$status}");
            }
        }
    }

    #[DataProvider('privilegedRoutes')]
    public function test_route_rejects_anonymous(string $method, string $uri, array $allowed): void
    {
        $this->seed(RoleSeeder::class);

        $this->assertContains($this->json($method, $uri)->status(), [401, 403, 302],
            "anonymous MUST NOT reach {$method} {$uri}");
    }
}
```

> A route reached with a valid role may legitimately return 422 (missing body). That is why the allowed-case asserts `assertNotSame(403)` rather than a success code — the matrix tests *authorization*, not validation.

#### `tests/Feature/Security/AuthenticationHardeningTest.php`

```php
<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AuthenticationHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    protected function tearDown(): void
    {
        RateLimiter::clear('');   // see Trap T-2
        parent::tearDown();
    }

    /** Establishes a guest session the way an attacker would: one malformed POST. */
    private function establishGuestSession(): void
    {
        $this->postJson('/api/cart/checkout', ['items' => 'not-an-array']);
    }

    /** F-01 */
    public function test_guest_login_is_rate_limited(): void
    {
        User::factory()->create([
            'email' => 'victim@example.com',
            'password' => Hash::make('correct-horse-battery'),
        ])->assignRole('visitor');

        $this->establishGuestSession();

        $lockedOut = false;
        for ($i = 1; $i <= 15; $i++) {
            $response = $this->postJson('/api/guest/login', [
                'email' => 'victim@example.com',
                'password' => "wrong-{$i}",
            ]);

            if ($response->status() === 429
                || str_contains(json_encode($response->json()), 'seconds')) {
                $lockedOut = true;
                break;
            }
        }

        $this->assertTrue($lockedOut,
            'F-01: /api/guest/login accepted 15 wrong passwords without locking out.');
    }

    /** F-01 - a cart merge must never target a staff account. */
    public function test_guest_login_refuses_non_visitor_accounts(): void
    {
        User::factory()->create([
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
        ])->assignRole('admin');

        $this->establishGuestSession();

        $this->postJson('/api/guest/login', [
            'email' => 'admin@example.com',
            'password' => 'password',
        ])->assertForbidden();
    }

    /** F-16 */
    public function test_confirm_password_is_rate_limited(): void
    {
        $user = User::factory()->create()->assignRole('visitor');

        $lockedOut = false;
        for ($i = 1; $i <= 15; $i++) {
            if ($this->actingAs($user)
                ->postJson('/confirm-password', ['password' => "guess-{$i}"])
                ->status() === 429) {
                $lockedOut = true;
                break;
            }
        }

        $this->assertTrue($lockedOut,
            'F-16: /confirm-password accepted 15 wrong passwords without throttling.');
    }

    /** F-13 - the response must not reveal whether the address is registered. */
    public function test_password_reset_does_not_enumerate_accounts(): void
    {
        User::factory()->create(['email' => 'real@example.com'])->assignRole('visitor');

        $known = $this->postJson('/forgot-password', ['email' => 'real@example.com']);
        $unknown = $this->postJson('/forgot-password', ['email' => 'nobody@example.com']);

        $this->assertSame($known->status(), $unknown->status(),
            'F-13: status code differs between known and unknown email.');
        $this->assertSame($known->json(), $unknown->json(),
            'F-13: response body differs between known and unknown email.');
    }

    /** F-06 - a malformed request must not create a permanent user row. */
    public function test_malformed_checkout_does_not_provision_a_user(): void
    {
        $before = User::count();

        $this->postJson('/api/cart/checkout', ['items' => 'garbage'])
            ->assertUnprocessable();

        $this->assertSame($before, User::count(),
            'F-06: a validation failure still created a guest user row.');
    }
}
```

**Acceptance:** the new suite fails; `php artisan test` shows the original **211 still passing** alongside the new failures. Commit red.

---

### TASK 1.0 — Patch vulnerable dependencies · S · F-11

Do this while the baseline is green so any breakage is unambiguous.

```powershell
composer update --with-all-dependencies
composer audit
npm audit fix
```

Then edit `package.json` — the current constraint pins axios **below** its fix for the XSRF-token cross-origin leak (GHSA-xx6v-rp6x-q39c), which is directly relevant to this app's cookie-CSRF scheme:

```diff
-        "axios": ">=1.11.0 <=1.14.0",
+        "axios": "^1.19.0",
```

```powershell
npm install
npm audit --omit=dev
npm run build
php artisan test
```

**Acceptance:** `composer audit` clean; `npm audit --omit=dev` clean; `npm run build` succeeds; 211 tests pass.
**Rollback:** `git checkout composer.lock package-lock.json package.json && composer install && npm install`.

---

### TASK 1.1 — Close the second credential path · S · **F-01 CRITICAL**

**File 1:** `routes/web.php`

**Current:**
```php
        Route::post('guest/login', [GuestController::class, 'login']);
```
**Replace with:**
```php
        Route::post('guest/login', [GuestController::class, 'login'])
            ->middleware('throttle:5,1');
```

**File 2:** `app/Http/Controllers/Api/GuestController.php`

Add imports:
```php
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
```

**Current code (match exactly):**
```php
        $target = User::where('email', $validated['email'])->first();

        if (! $target || ! Hash::check($validated['password'], $target->password)) {
            throw ValidationException::withMessages([
                'email' => 'These credentials do not match our records.',
            ]);
        }
```

**Replace with:**
```php
        // Mirrors LoginRequest::throttleKey() so this path cannot be used to
        // sidestep the primary login limiter (audit F-01).
        $throttleKey = 'guest-login:'.Str::transliterate(
            Str::lower($validated['email']).'|'.$request->ip()
        );

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            throw ValidationException::withMessages([
                'email' => trans('auth.throttle', [
                    'seconds' => $seconds,
                    'minutes' => ceil($seconds / 60),
                ]),
            ]);
        }

        $target = User::where('email', $validated['email'])->first();

        if (! $target || ! Hash::check($validated['password'], $target->password)) {
            RateLimiter::hit($throttleKey, 60);

            throw ValidationException::withMessages([
                'email' => 'These credentials do not match our records.',
            ]);
        }

        // A guest cart is only ever merged into a visitor account. Staff sign in
        // through /login, which is separately throttled and audited; allowing a
        // staff account here made this endpoint a full privilege-escalation path.
        if (! $target->hasRole('visitor')) {
            abort(403);
        }

        RateLimiter::clear($throttleKey);
```

**Accepted trade-off (document in the docblock):** a staff member using guest checkout must sign in via `/login`. That is correct behaviour.

**Optional, higher value if time allows:** delete the hand-rolled `Hash::check` and route this through a shared throttled action that calls `Auth::attempt()`, so the codebase has exactly **one** credential-verification path. That removes the root cause (duplication) instead of patching the copy.

**Acceptance:**
```powershell
php artisan test --filter=AuthenticationHardeningTest
php artisan test --filter=GuestCheckoutTest    # 12 existing assertions must stay green
```

---

### TASK 1.2 — Booking state machine · S · F-05

**⚠ Ships with Tasks 2.1 + 2.2.** See release coupling in Part 1.

**File:** `app/Http/Controllers/Api/BookingController.php`

Add a class constant:
```php
    /**
     * Allowed status transitions. `cancelled` is terminal - re-confirming a
     * cancelled booking double-books a room that has already been resold
     * (audit F-05).
     */
    private const TRANSITIONS = [
        'pending' => ['confirmed', 'cancelled'],
        'confirmed' => ['cancelled'],
        'cancelled' => [],
    ];
```

**Current `update()` body (match exactly):**
```php
        $validated = $request->validate([
            'status' => ['required', 'in:confirmed,cancelled'],
        ]);

        $booking->update($validated);

        return response()->json($booking);
```

**Replace with:**
```php
        // Only staff may confirm. A visitor pays via POST /api/bookings/pay,
        // which records a Payment and owns the transition server-side (F-02).
        $isStaff = $user->hasRole('hotel_manager');

        $validated = $request->validate([
            'status' => ['required', $isStaff ? 'in:confirmed,cancelled' : 'in:cancelled'],
        ]);

        $next = $validated['status'];

        if (! in_array($next, self::TRANSITIONS[$booking->status] ?? [], true)) {
            throw new ValidationException(validator([], []), response()->json([
                'message' => "A {$booking->status} booking cannot become {$next}.",
                'errors' => ['status' => ["A {$booking->status} booking cannot become {$next}."]],
            ], 422));
        }

        $booking->update(['status' => $next]);

        return response()->json($booking);
```

Simpler equivalent if you prefer (add `use Illuminate\Validation\ValidationException;`):
```php
        if (! in_array($next, self::TRANSITIONS[$booking->status] ?? [], true)) {
            throw ValidationException::withMessages([
                'status' => "A {$booking->status} booking cannot become {$next}.",
            ]);
        }
```
Use this second form — it matches the idiom used throughout the codebase.

**Must keep passing** (verified to exist):
* `test_owner_can_cancel_their_booking`
* `test_cancelling_a_booking_frees_the_room_for_the_same_dates`
* `test_user_cannot_cancel_someone_elses_booking`
* `hotel_manager` PATCH → `confirmed` returns 200 (verified working today)

> No existing test asserts that a **visitor** can PATCH to `confirmed`. Verified. The backend change is test-safe.

---

### TASK 1.3 — Park capacity guards · S · F-04

**File:** `app/Http/Controllers/Api/ThemeParkController.php`, method `cancelBooking()`

**Current code (match exactly):**
```php
        DB::transaction(function () use ($booking) {
            EventSlot::lockForUpdate()->findOrFail($booking->event_slot_id)
                ->increment('available_capacity', $booking->ticket_count);
            $booking->update(['status' => 'cancelled']);
        });

        return response()->json($booking->fresh());
```

**Replace with:**
```php
        // Idempotent: without this, repeated DELETEs each returned the seats
        // again and inflated the slot past its physical capacity (audit F-04).
        if ($booking->status === 'cancelled') {
            return response()->json($booking);
        }

        if ($booking->status === 'used') {
            throw ValidationException::withMessages([
                'status' => 'This ticket has already been used and cannot be cancelled.',
            ]);
        }

        DB::transaction(function () use ($booking) {
            $slot = EventSlot::with('event')->lockForUpdate()->findOrFail($booking->event_slot_id);

            // Clamped so a future accounting bug can never oversell the slot.
            $slot->update([
                'available_capacity' => min(
                    $slot->event->capacity_per_slot,
                    $slot->available_capacity + $booking->ticket_count
                ),
            ]);

            $booking->update(['status' => 'cancelled']);
        });

        return response()->json($booking->fresh());
```

`ValidationException` is already imported in this file. **While here**, check every other counter restore for the same omission — `FerryController::cancelTicket()` is the correct reference implementation.

---

### TASK 1.4 — Park ticket validation guards · S · F-09

**File:** `app/Http/Controllers/Api/ThemeParkTicketController.php`, method `validateTicket()`

**Current code (match exactly):**
```php
        if ($booking->status === 'used') {
            throw ValidationException::withMessages([
                'status' => 'This ticket has already been used.',
            ]);
        }

        $booking->update(['status' => 'used']);
```

**Replace with:**
```php
        if ($booking->status === 'used') {
            throw ValidationException::withMessages([
                'status' => 'This ticket has already been used.',
            ]);
        }

        // A refunded ticket still admitted its holder, and because cancelling
        // had already returned the seat to inventory the slot went oversold by
        // exactly the admitted party (audit F-09).
        if ($booking->status === 'cancelled') {
            throw ValidationException::withMessages([
                'status' => 'This ticket has been cancelled.',
            ]);
        }

        if ($booking->slot->status !== 'scheduled') {
            throw ValidationException::withMessages([
                'status' => 'This time slot is not running.',
            ]);
        }

        $booking->update(['status' => 'used']);
```

---

### TASK 1.5 — Server-side sailing scope · S · F-08

**File 1:** `app/Http/Controllers/Api/FerryController.php`, method `validateTicket()`

Insert immediately after the role check and **before** the `used`/`cancelled` guards:

```php
        $validated = $request->validate([
            'schedule_id' => ['required', 'exists:ferry_schedules,id'],
        ]);

        // The only thing stopping a wrong-sailing scan used to be a computed
        // property in the SPA; the server accepted a 10-day-old ticket from a
        // different departure (audit F-08).
        if ((int) $ticket->schedule_id !== (int) $validated['schedule_id']) {
            throw ValidationException::withMessages([
                'schedule_id' => 'This ticket is for a different departure.',
            ]);
        }

        $schedule = $ticket->schedule;

        if ($schedule->status !== 'scheduled') {
            throw ValidationException::withMessages([
                'schedule_id' => $schedule->status === 'cancelled'
                    ? 'This departure has been cancelled.'
                    : 'This departure has already sailed.',
            ]);
        }

        if (! $schedule->departure_date->isToday()) {
            throw ValidationException::withMessages([
                'schedule_id' => 'This ticket is not for today\'s departure.',
            ]);
        }
```

> Check `FerrySchedule::casts()` for `departure_date => 'date'`. If it is not cast, use
> `! \Illuminate\Support\Carbon::parse($schedule->departure_date)->isToday()` instead.

**File 2:** `resources/js/stores/ferry.js` — anchor on this **exact string**:

```js
        async validateTicketOnSite(ticketOrId) {
            const id = typeof ticketOrId === 'object' ? ticketOrId.id : ticketOrId;
            const { data } = await axios.post(`/api/ferry/tickets/${id}/validate`);
            return data;
        },
```

**Replace with:**
```js
        async validateTicketOnSite(ticketOrId, scheduleId) {
            const id = typeof ticketOrId === 'object' ? ticketOrId.id : ticketOrId;
            const { data } = await axios.post(`/api/ferry/tickets/${id}/validate`, {
                schedule_id: scheduleId,
            });
            return data;
        },
```

**File 3:** `resources/js/Pages/Ferry/TicketValidationView.vue` — two call sites. Anchor on the strings, not line numbers (§0.4):

```js
// in confirmUsed()
ticket.value = await ferryStore.validateTicketOnSite(ticket.value.id);
// becomes
ticket.value = await ferryStore.validateTicketOnSite(ticket.value.id, selectedSchedule.value.id);
```
```js
// in the party bulk-board handler
await ferryStore.validateTicketOnSite(t.id);
// becomes
await ferryStore.validateTicketOnSite(t.id, selectedSchedule.value.id);
```

Leave the `scheduleMismatch` computed in place — it is now UX only, which is correct.

---

### TASK 2.1 — `payments` table + server-owned settle action · M · F-02

**Context you need:** `PATCH /api/bookings/{id} {status:'confirmed'}` is not a stray endpoint — it *is* the visitor's payment step. `BookingConfirmationView.pay()` and `PaymentsDueMenu` both call it. Card details never reach the server (`PaymentForm.vue` states they are "deliberately never emitted"). This task moves the transition behind a server-owned action that records it.

#### 2.1a — Migration

`php artisan make:migration create_payments_table`

```php
Schema::create('payments', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->morphs('payable');                 // Booking, FerryTicket, EventBooking
    $table->decimal('amount', 10, 2);
    $table->string('method');                  // card | cash
    $table->string('status')->default('captured');
    $table->string('reference')->unique();     // server-generated, never from the client
    $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamps();
});
```

#### 2.1b — `app/Models/Payment.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Payment extends Model
{
    protected $fillable = [
        'user_id', 'payable_type', 'payable_id',
        'amount', 'method', 'status', 'reference', 'recorded_by',
    ];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    public function payable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

#### 2.1c — `HotelBookingService::settle()`

Add to `app/Services/HotelBookingService.php` (imports: `App\Models\Payment`, `App\Models\User`, `Illuminate\Support\Str`):

```php
    /**
     * The single owner of the pending -> confirmed transition.
     *
     * Amount is taken from the booking, never from the request: the client
     * computes a total for display only, and trusting it would let a caller
     * name their own price. Callers must run this inside a DB transaction.
     */
    public function settle(Collection $bookings, User $actor, string $method = 'card'): Collection
    {
        return $bookings->map(function (Booking $booking) use ($actor, $method) {
            Payment::create([
                'user_id' => $booking->user_id,
                'payable_type' => $booking->getMorphClass(),
                'payable_id' => $booking->id,
                'amount' => $booking->total_price,
                'method' => $method,
                'status' => 'captured',
                'reference' => 'PAY-'.Str::upper(Str::random(12)),
                'recorded_by' => $actor->id,
            ]);

            $booking->update(['status' => 'confirmed']);

            return $booking->fresh();
        })->values();
    }
```

**Replace `confirm()` with `settle()` everywhere.** `CartController::checkout()` currently calls `$this->hotelBookings->confirm($bookings)` — that path confirms with **no payment record**, the same gap by a different door. One confirm path, or the drift returns. In `CartController`:

```php
$this->hotelBookings->settle($bookings, $request->user());
```
(`checkout()` will need the `Request` threaded into the transaction closure — it is already in scope via `$userId`; add `$request` to the `use (...)` list, or pass `$request->user()` in as a variable.)

Once no caller remains, delete `confirm()`.

#### 2.1d — Endpoint

`routes/web.php`, inside the `auth` + `api` group, **above** the `bookings/{booking}` routes so it is not swallowed by the wildcard:

```php
        Route::post('bookings/pay', [BookingController::class, 'pay']);
```

`BookingController::pay()`:

```php
    /**
     * Settles one or more of the caller's own pending bookings. Accepts a group
     * because the UI pays a multi-room stay in one action.
     */
    public function pay(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'booking_ids' => ['required', 'array', 'min:1', 'max:20'],
            'booking_ids.*' => ['integer', 'exists:bookings,id'],
        ]);

        $paid = DB::transaction(function () use ($request, $validated) {
            $bookings = Booking::whereIn('id', $validated['booking_ids'])
                ->lockForUpdate()
                ->get();

            foreach ($bookings as $booking) {
                if ($booking->user_id !== $request->user()->id) {
                    abort(403);
                }

                if ($booking->status !== 'pending') {
                    throw ValidationException::withMessages([
                        'booking_ids' => "Booking {$booking->reference_code} is {$booking->status} and cannot be paid.",
                    ]);
                }
            }

            return $this->bookings->settle($bookings, $request->user());
        });

        return response()->json($paid);
    }
```

Add `use Illuminate\Validation\ValidationException;` to the controller.

**Acceptance:** a `Payment` row exists per confirmed booking; amount equals `total_price`; visitor PATCH→`confirmed` returns 422; manager PATCH→`confirmed` returns 200; `CartCheckoutTest` green.

---

### TASK 2.2 — Rewire the SPA payment flow · S · F-02

**File:** `resources/js/stores/hotel.js` — anchor on this **exact string**:

```js
        async confirmBooking(id, { silent = false } = {}) {
            const { data } = await axios.patch(`/api/bookings/${id}`, { status: 'confirmed' }, { silent401: silent });
            this._syncBooking(data);
            return data;
        },
```

**Replace with two distinct actions — two callers, two intents, do not share one:**

```js
        // Visitor paying for their own bookings. The server records a Payment
        // and owns the pending -> confirmed transition.
        async payBookings(ids, { silent = false } = {}) {
            const { data } = await axios.post(
                '/api/bookings/pay',
                { booking_ids: ids },
                { silent401: silent }
            );
            data.forEach((booking) => this._syncBooking(booking));
            return data;
        },

        // Front-desk staff marking a booking settled (e.g. cash at the desk).
        // Distinct from payBookings: different actor, different authorization.
        async staffConfirmBooking(id) {
            const { data } = await axios.patch(`/api/bookings/${id}`, { status: 'confirmed' });
            this._syncBooking(data);
            return data;
        },
```

Then update `confirmBookings` (the existing plural wrapper that `Promise.all`s over `confirmBooking`) to delegate to `payBookings` in **one** request instead of N.

**Call sites to update** — anchor on strings:

| File | Change |
|---|---|
| `Pages/Visitor/BookingConfirmationView.vue` | `hotelStore.confirmBookings(ids.value, { silent: true })` → `hotelStore.payBookings(ids.value, { silent: true })` |
| `Components/PaymentsDueMenu.vue` | its pay handler → `payBookings(selected)` |
| `Pages/Manager/HotelDashboardView.vue` | `hotelStore.confirmBooking(booking.id)` → `hotelStore.staffConfirmBooking(booking.id)` |

Also replace the now-inaccurate comment in `BookingConfirmationView.vue`:
```js
// Payment gateway integration is out of scope for now - this simply
// marks every booking in the group confirmed once the mock card form
// is filled in.
```
with a note that the server records the payment and owns the transition.

**Acceptance:** `npm run build` clean; visitor pay works end to end; manager confirm works; both produce/behave as expected.

---

### TASK 3.1 — Rate limiting · S · F-07

**File 1:** `app/Providers/AppServiceProvider.php` — add to `boot()` (imports: `Illuminate\Cache\RateLimiting\Limit`, `Illuminate\Http\Request`, `Illuminate\Support\Facades\RateLimiter`):

```php
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)
            ->by($request->user()?->id ?: $request->ip()));

        RateLimiter::for('auth', fn (Request $request) => Limit::perMinute(5)
            ->by($request->ip()));

        RateLimiter::for('writes', fn (Request $request) => Limit::perMinute(20)
            ->by($request->user()?->id ?: $request->ip()));
```

**File 2:** `bootstrap/app.php` — inside `withMiddleware`, add `$middleware->throttleApi('api');`
(verified present in Laravel 13 at `Foundation/Configuration/Middleware.php:739`).

**File 3:** `routes/auth.php` — add `->middleware('throttle:auth')` to `register`, `login`, `password.email`, `password.store`, and `confirm-password` (Task 3.3).

**File 4:** `routes/web.php` — apply `throttle:writes` to the booking/checkout group (coordinate with Task 3.2, which changes the same group).

**⚠ Trap T-2 — see Part 3.** Limiters leak between tests. Add `RateLimiter::clear()` in `tearDown`, or `withoutMiddleware(ThrottleRequests::class)` in suites that hammer endpoints. Expect some existing tests to start returning 429 — the fix is to disable throttling in *those* tests, not to weaken the limits.

---

### TASK 3.2 — Guest lifecycle · S · F-06

Three parts, in ascending order of value.

**1. Throttle before provisioning — order matters.** `routes/web.php`:

**Current:**
```php
Route::middleware(AutoLoginGuest::class)->prefix('api')->group(function () {
```
**Replace with:**
```php
// throttle FIRST: a rejected request must not provision a user row.
Route::middleware(['throttle:10,1', AutoLoginGuest::class])->prefix('api')->group(function () {
```

**2. Validate before provisioning — the actual fix.** The throttle only caps the rate; a valid-rate attacker still writes rows, and any malformed body writes one. Move guest creation out of middleware so it happens only after validation succeeds.

Recommended shape: delete the middleware from the group and call an explicit helper as the first line of each of the three controller actions (`BookingController::store`, `ThemeParkController::bookSlot`, `CartController::checkout`), *after* `$request->validate(...)`:

```php
// app/Support/GuestSession.php
public static function ensure(Request $request): User
{
    if (! Auth::check()) {
        Auth::login(User::createGuest());
        $request->session()->regenerate();
    }

    return $request->user();
}
```

Then in each action, replace `$request->user()->id` with `GuestSession::ensure($request)->id` **after** validation. Keep `AutoLoginGuest` as a deprecated shim or delete it once unused.

> `GuestCheckoutTest` asserts guest provisioning behaviour — expect to update *when* the row is created (after validation), not whether. Read those 12 assertions before editing.

**3. Prune.** `routes/console.php`, beside the existing `Schedule::command('schedules:generate')->daily();`:

```php
Schedule::call(function () {
    User::where('is_guest', true)
        ->where('created_at', '<', now()->subDays(7))
        ->whereDoesntHave('bookings')
        ->each(fn (User $guest) => $guest->delete());
})->daily();
```

This requires a `bookings()` relation on `User` — verify it exists; add `hasMany(Booking::class)` if not. Consider also excluding guests with `ferryTickets` or `eventBookings`.

---

### TASK 3.3 — Throttle `/confirm-password` · XS · F-16

`routes/auth.php`:
```php
    Route::post('confirm-password', [ConfirmablePasswordController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('password.confirm');
```

---

### TASK 4.1 — Consistent `is_active` enforcement · M · F-24

**Requires Task 0.1** (needs `role:` middleware for the moved route).

**Critical constraint.** `resources/js/stores/themepark.js` `fetchEvents()` calls `/api/themepark/events`, which filters `is_active` — and **both** the visitor home *and* the staff `EventManagementView` use it. So toggling an event inactive already removes it from the management list with no way to toggle it back. **This is an existing bug.** Adding naive filters to hotels would reproduce it. Every filter must ship with a role-gated management scope.

**1. One owner for the rule.** Add to each of `Hotel`, `ThemeParkEvent`, `Ferry`:

```php
    /**
     * Public callers see only active records; staff may opt into the full list.
     * One definition of "visible", because `is_active` was previously honoured
     * in three endpoints and ignored in four (audit F-24).
     */
    public function scopeVisibleTo(Builder $query, ?User $user, bool $wantsAll = false): Builder
    {
        $isStaff = $user?->hasAnyRole([
            'hotel_manager', 'themepark_staff', 'ferry_operator', 'admin',
        ]) ?? false;

        return $wantsAll && $isStaff ? $query : $query->where('is_active', true);
    }
```

**2. Apply it.**

| Site | Change |
|---|---|
| `HotelController::index` | `Hotel::query()->visibleTo($request->user(), $request->boolean('all'))->paginate($perPage)` |
| `HotelController::show` | `abort(404)` when inactive and caller is not staff |
| `FerryController::schedules` | constrain the eager-load: `->with(['ferry' => fn ($q) => $q->visibleTo($request->user(), $request->boolean('all'))])`, **and** filter out schedules whose ferry is hidden — an unfiltered `whereHas` is the actual leak |
| `ThemeParkController::show` | `abort(404)` when inactive and caller is not staff |
| `ThemeParkController::index` | accept `?all=1` for the management screen |

**3. Move the staff calendar endpoint.** `themepark/slots` is registered in the **public** group at `routes/web.php` while its own docblock says it is "for the staff scheduling calendar". Move it into the `auth` group:

```php
        Route::get('themepark/slots', [ThemeParkController::class, 'allSlots'])
            ->middleware('role:themepark_staff|admin');
```

**4. Update the SPA** so staff screens pass the flag — this also **repairs the one-way toggle bug**:

| Store action | Change |
|---|---|
| `themepark.js` `fetchEvents()` | accept `{ all = false }`, pass `params: { all: all ? 1 : undefined }` |
| `hotel.js` `fetchHotels()` | same |
| `themepark.js` `fetchAllSlots()` | unchanged call, but it is now authenticated — confirm staff-only screens are its only callers |

Then pass `{ all: true }` from `EventManagementView`, `RoomManagementView`, `FleetManagementView`.

**Acceptance:** anonymous callers never see an inactive hotel/ferry/event through **any** endpoint (`/api/hotels`, `/api/hotels/{id}`, `/api/ferry/schedules`, `/api/themepark/events`, `/api/themepark/events/{id}`, `/api/themepark/slots`); **and** a staff user can still list and re-activate an inactive record. Write a regression test for the second half — it is broken today.

---

### TASK 4.2 — Security headers · S · F-12

`php artisan make:middleware SecurityHeaders`, then append to the `web` group in `bootstrap/app.php`.

```php
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->add([
            'X-Frame-Options' => 'DENY',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'geolocation=(), microphone=(), camera=(self)',
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
```

**Three things this will break if you are not careful:**
1. **The inline theme script** in `resources/views/app.blade.php` (the `localStorage.getItem('theme')` IIFE) is blocked by `default-src 'self'`. Prefer a per-request nonce over `'unsafe-inline'` for `script-src`.
2. **The island map** — `IslandMap.vue` uses Leaflet with satellite tiles. The `img-src` hosts above cover OpenStreetMap and ArcGIS; **verify against the actual tile URL in that component**, which another session is currently editing (§0.4).
3. **The QR camera scanner** needs `camera=(self)` in `Permissions-Policy` — without it `QrCameraScanner.vue` silently fails.

Add `Strict-Transport-Security: max-age=31536000; includeSubDomains` at the TLS terminator in production, not here.

**Acceptance:** manually exercise the homepage map, both QR scanner screens, and the admin dashboard charts with the browser console open — zero CSP violations.

---

### TASK 4.3 — Config hardening · S · F-14

`.env.example`:
```diff
-APP_DEBUG=true
+APP_DEBUG=false
-LOG_LEVEL=debug
+LOG_LEVEL=warning
-DB_USERNAME=root
-DB_PASSWORD=
+DB_USERNAME=tpms
+DB_PASSWORD=
 SESSION_DRIVER=database
 SESSION_LIFETIME=120
-SESSION_ENCRYPT=false
+SESSION_ENCRYPT=true
+SESSION_SECURE_COOKIE=true
```

`compose.yaml`:
```diff
     mysql:
         ports:
-            - '${FORWARD_DB_PORT:-3306}:3306'
+            - '127.0.0.1:${FORWARD_DB_PORT:-3306}:3306'
         environment:
-            MYSQL_ALLOW_EMPTY_PASSWORD: 1
```

`AppServiceProvider::boot()`:
```php
        if (app()->isProduction() && config('app.debug')) {
            throw new RuntimeException('APP_DEBUG must be false in production.');
        }
```

Add a deployment checklist to `README.md`: `APP_ENV=production`, `APP_DEBUG=false`, unique `APP_KEY`, HTTPS only, `SESSION_SECURE_COOKIE=true`, `php artisan config:cache route:cache`.

> **Do not** set `SESSION_SECURE_COOKIE=true` in your local `.env` — it breaks `http://localhost`. `.env.example` only.

---

### TASK 4.4 — Uniform reset response + CORS · S · F-13, F-17

**File 1:** `app/Http/Controllers/Auth/PasswordResetLinkController.php` — replace the whole `store()` body:

```php
        $request->validate(['email' => 'required|email']);

        // Deliberately ignores the broker's status: a 422 for unknown addresses
        // let anyone test whether a person holds an account (audit F-13).
        Password::sendResetLink($request->only('email'));

        return response()->json([
            'status' => __('If that email address is registered, a reset link is on its way.'),
        ]);
```

Remove the now-unused `ValidationException` import if nothing else uses it. **Check the SPA** — `Pages/Auth/ForgotPassword.vue` may render a validation error that can no longer occur; it should show the status message instead.

**File 2:** create `config/cors.php` (there is none, so the framework default `allowed_origins => ['*']` applies):

```php
<?php

return [
    'paths' => ['api/*'],
    'allowed_methods' => ['GET', 'POST', 'PATCH', 'DELETE'],
    'allowed_origins' => [env('APP_URL', 'http://localhost')],
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => false,
];
```

---

### TASK 4.6 — PII minimisation · S · F-18

Replace `with('user')` + raw model serialization with projections or purpose-scoped Resources. Follow the existing `HotelResource`/`RoomResource` pattern.

| File | Method | Change |
|---|---|---|
| `BookingController` | `index` | `with(['room.hotel', 'user:id,name'])` |
| `FerryController` | `showTicket`, `passengers`, `partyStatus` | `user:id,name`, or a `PassengerResource` exposing only name / seat / reference / status |
| `ThemeParkTicketController` | `showTicket` | `user:id,name` |

`$hidden` already covers `password` and `remember_token`, so no credential material leaks today — this removes `email`, `email_verified_at` and `is_guest` from staff-facing payloads. **Check the consuming SPA views** for any use of the removed fields before shipping.

---

### TASK 5.1 — Opaque reference codes · M · F-03

**Scope, verified:** `reference_code` appears in 20 SPA places, but **18 only render it**. Exactly two functions parse it into a primary key:

| File | Function |
|---|---|
| `Pages/Ferry/TicketValidationView.vue` | `parseScan()` |
| `Pages/ThemePark/TicketValidationView.vue` | `parseBookingId()` |

**Keep the attribute name `reference_code`** → all 18 display sites need zero changes.

**1. Migration** — one migration, three tables (`bookings`, `ferry_tickets`, `event_bookings`):

```php
public function up(): void
{
    foreach (['bookings' => 'VFN-B', 'ferry_tickets' => 'VFN-T', 'event_bookings' => 'VFN-E'] as $table => $prefix) {
        Schema::table($table, function (Blueprint $t) {
            $t->string('reference_code', 32)->nullable()->after('id');
        });

        // Query builder, NOT Eloquent: the models still have a reference_code
        // accessor at this point and it would shadow the column.
        DB::table($table)->select('id')->orderBy('id')->chunk(500, function ($rows) use ($table, $prefix) {
            foreach ($rows as $row) {
                DB::table($table)->where('id', $row->id)->update([
                    'reference_code' => $prefix.Str::upper(Str::random(12)),
                ]);
            }
        });

        Schema::table($table, function (Blueprint $t) {
            $t->string('reference_code', 32)->nullable(false)->unique()->change();
        });
    }
}
```

> **Trap:** back-fill with the **query builder**, not Eloquent — the accessor still exists during the migration and would shadow the column. Also verify `doctrine/dbal` is not needed for `->change()` on your Laravel version; if the three-step add/backfill/change is awkward on SQLite, add the column as nullable+unique and enforce non-null in the model's `creating` hook instead.

**2. Models** — in each of `Booking`, `FerryTicket`, `EventBooking`:
* delete the `referenceCode(): Attribute` accessor
* remove `'reference_code'` from `$appends`
* add `'reference_code'` to `$fillable`
* add:

```php
    protected static function booted(): void
    {
        static::creating(function (self $model) {
            $model->reference_code ??= 'VFN-B'.Str::upper(Str::random(12));  // prefix per model
        });
    }
```

The prefix letter stays — it routes the scan to the right lookup and is not a secret.

**3. Lookup endpoints** (role-gated exactly as the current `showTicket` is):
```php
        Route::get('ferry/tickets/lookup', [FerryController::class, 'lookupByReference']);
        Route::get('themepark/tickets/lookup', [ThemeParkTicketController::class, 'lookupByReference']);
```
Each validates `code` and resolves `where('reference_code', $code)->firstOrFail()`. Register **above** any `{param}` wildcard on the same prefix so it is not swallowed.

**4. Scanners** — keep prefix-based type detection, drop id extraction:
```js
// Pages/Ferry/TicketValidationView.vue
const parseScan = (raw) => {
    const code = String(raw ?? '').trim().toUpperCase();
    const m = code.match(/^VFN-([BT])[A-Z0-9]+$/);
    if (!m) return null;
    return { type: m[1] === 'B' ? 'booking' : 'ticket', code };
};
```
Then `lookupParsed` passes `parsed.code` to the lookup endpoint instead of `parsed.id`. Keep manual entry working.

**Acceptance:** codes non-sequential and unguessable; scanning a real QR still validates; `VFN-T0001` returns 404. `FerryTicketTest` / `ThemeParkTicketTest` may assert on code format — **update** those assertions, do not delete them.

**Rollback:** this is the only task with a data migration. Test on a seeded copy first: `php artisan migrate:fresh --seed`, then run the migration, and confirm no unique-index collisions.

---

### TASK 6.1 — Route-level gates + named permissions · L · F-10

**Do last** — touches every route file and conflicts with everything else. **Requires Task 0.1.**

1. **Declarative coarse boundary.** Wrap groups so authorization cannot be omitted by forgetting a line in a controller:
```php
Route::middleware(['auth', 'role:admin'])->prefix('api/admin')->group(function () { ... });
Route::middleware(['auth', 'role:ferry_operator|admin'])->prefix('api/ferry')->group(function () { ... });
Route::middleware(['auth', 'role:themepark_staff|admin'])->prefix('api/themepark')->group(function () { ... });
```
Careful: several prefixes mix public, visitor and staff routes (e.g. `api/themepark` has public event listing *and* staff slot management). Split each prefix into public / visitor / staff sub-groups rather than over-gating. **The Task 0.3 matrix is what tells you when you got it wrong.**

2. **Named permissions.** Seed `tickets.validate`, `tickets.sell`, `fleet.manage`, `schedules.manage`, `events.manage`, `users.manage`, `promotions.manage`, `map.manage` onto roles in `RoleSeeder`; check `$user->can('tickets.validate')`. A role rename then touches one file instead of 24 call sites.

3. **Complete the policy set** — `Booking`, `EventBooking`, `FerryTicket`, `MapLocation`, `Promotion`, `User` — and delete the 24 inline `hasAnyRole()` checks in favour of `Gate::authorize`.

4. **The matrix must stay green through every step.** Run it after each group you convert, not at the end.

---

### TASK 6.2 — Tail hardening · M

| Finding | Change | Watch out |
|---|---|---|
| F-15 | `User implements MustVerifyEmail`; `verified` middleware on transactional routes; verify at `GuestController::claim` | `UserFactory` sets `email_verified_at => now()`, so the existing suite is safe. Guest checkout must keep working for unverified users. |
| F-19 | `validated_by`/`validated_at`/`cancelled_by` columns; an `audit` log channel in `config/logging.php`; log `Auth\Events\Failed` and `Lockout` | Without the auth-event logging, an F-01-style attack stays invisible |
| F-20 | add `$request->session()->regenerate();` to `GuestController::claim()` after the `update()` | one line |
| F-21 | re-encode / strip metadata on upload via a spatie conversion | needs Task 4.2's `nosniff` to be worthwhile |
| F-22 | remove `is_guest` from `User`'s `#[Fillable]`; set via `forceFill` in `createGuest()` and `claim()` | note this model uses Laravel 13 **attribute-based** `#[Fillable]`/`#[Hidden]`, not `$fillable` properties |
| F-23 | TOTP for `admin`/staff; remove unused `laravel/sanctum` | sanctum is installed and migrated but no route, trait or middleware uses it |

---

## PART 3 — TRAPS

Each of these will cost an hour if you meet it cold.

| # | Trap | Mitigation |
|---|---|---|
| **T-1** | `role:` middleware throws `Target class [role] does not exist` | Task 0.1 first. Not optional. |
| **T-2** | Rate limiters **leak between tests** — `CACHE_STORE=array` persists within a test, and a tripped limiter makes later tests 429 | `RateLimiter::clear($key)` in `tearDown`, or `withoutMiddleware(ThrottleRequests::class)` in suites that hammer endpoints. Expect existing tests to start 429-ing after Task 3.1; fix the tests, not the limits. |
| **T-3** | Landing Task 1.2 without 2.1/2.2 **breaks the visitor pay button** | Ship as one release, or take only 1.2's transition table |
| **T-4** | Task 5.1 back-fill via Eloquent silently writes the **accessor** value | Use the query builder during the migration |
| **T-5** | CSP breaks the inline theme script, Leaflet tiles, and the QR camera | Nonce for `script-src`; verify tile hosts against `IslandMap.vue`; `camera=(self)` |
| **T-6** | `/api/admin/stats` 500s on SQLite, so it cannot enter the matrix | Task 0.2 before 0.3 |
| **T-7** | Line numbers in `resources/js/` **drift** — another session is editing that tree | Anchor every frontend edit on a unique code string (§0.4) |
| **T-8** | `php` is not on the bash PATH | Use the PowerShell tool for `php`/`composer`/`npm` |
| **T-9** | New route swallowed by a wildcard (`bookings/pay` vs `bookings/{booking}`; `tickets/lookup` vs `tickets/{ticket}`) | Register literal paths **above** parameterised ones |
| **T-10** | `User` uses Laravel 13 attribute-based `#[Fillable]` / `#[Hidden]`, not properties | Do not add a `$fillable` array — edit the attribute |

---

## PART 4 — DEFINITION OF DONE

A task is done when:

1. Its Phase-0 test is green.
2. The original **211 tests still pass**.
3. `AuthorizationMatrixTest` is green and includes any route the task added.
4. No new inline `hasAnyRole()` was introduced.
5. `npm run build` succeeds (frontend tasks only).
6. The commit contains no change to a security control without a corresponding test.

**Whole-programme done:** all 25 findings closed or explicitly deferred with rationale; `composer audit` and `npm audit --omit=dev` clean; CI gate added.

**Suggested CI gate** — `.github/workflows/ci.yml` running `php artisan test`, `composer audit`, and `npm audit --omit=dev`, failing the build on any. Without this, F-11 silently returns within a month.

---

## PART 5 — OUT OF SCOPE

Do not do these. Each was considered and rejected with reason.

| Not doing | Why |
|---|---|
| Real payment gateway | Out of scope for the course. Task 2.1 makes the *boundary* correct, so a gateway later replaces one service method. |
| Rewrite `Ferry::deck()` / layout system | Reviewed and sound: `normaliseGrid` rejects malformed input, capacity is derived server-side and never trusted from the client. |
| Field-level / at-rest encryption | No card or identity data is stored; card details never reach the server. Not justified. |
| Change the guest-checkout **UX** | The pattern is good product design. F-06 fixes its implementation, not the concept. |
| Concurrency fuzzing | `lockForUpdate()` usage was reviewed and is correct; the bugs found were missing state guards, not lock failures. |
| Touch the files in §0.4 | Another session owns them. |

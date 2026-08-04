<?php

namespace Tests;

use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    /**
     * A password that satisfies the policy in config/security.php.
     *
     * Use this whenever a test sets a password. `password` and `password123` no
     * longer pass: they are under the length minimum and both appear in breach
     * corpora, which is the point.
     */
    public const VALID_PASSWORD = 'lagoon ferry tuesday';

    // Roles must exist for registration; seeded on every database refresh.
    protected $seed = true;

    protected $seeder = RoleSeeder::class;

    protected function setUp(): void
    {
        parent::setUp();

        // Frontend assets are not built in the test environment.
        $this->withoutVite();

        // Rate limiter state lives in the cache, and the array store persists for
        // the whole PHPUnit process - RefreshDatabase does not touch it. Without
        // this, one test that deliberately trips a limiter throttles every test
        // that runs after it.
        Cache::flush();

        $this->fakeBreachedPasswordApi();
    }

    /** SHA-1 hash (uppercase) => occurrence count, for passwords to treat as breached. */
    protected array $breachedPasswordHashes = [];

    /** Whether the stubbed breach API should answer at all. */
    protected bool $breachApiAvailable = true;

    /**
     * The password policy checks candidates against the Have I Been Pwned range
     * API. Left unstubbed that is a real network call on every registration test:
     * slow, offline-hostile, and dependent on a third party's uptime.
     *
     * Registered as a single dynamic stub rather than a fixed response, because
     * repeated Http::fake() calls MERGE and the first registered stub wins - so a
     * per-test override would silently never apply.
     */
    protected function fakeBreachedPasswordApi(): void
    {
        Http::fake([
            'api.pwnedpasswords.com/*' => function ($request) {
                if (! $this->breachApiAvailable) {
                    return Http::response('service unavailable', 503);
                }

                // The real API returns every breached hash sharing the requested
                // 5-character prefix, as SUFFIX:COUNT lines.
                $prefix = strtoupper(substr($request->url(), -5));

                $lines = [];
                foreach ($this->breachedPasswordHashes as $hash => $count) {
                    if (str_starts_with($hash, $prefix)) {
                        $lines[] = substr($hash, 5).':'.$count;
                    }
                }

                return Http::response(implode("\n", $lines), 200);
            },
        ]);
    }

    /** Make the breach API report the given password as compromised. */
    protected function fakePasswordAsBreached(string $password, int $occurrences = 5_000): void
    {
        $this->breachedPasswordHashes[strtoupper(sha1($password))] = $occurrences;
    }

    /** Simulate the breach API being unreachable. The policy must fail open. */
    protected function fakeBreachApiUnavailable(): void
    {
        $this->breachApiAvailable = false;
    }
}

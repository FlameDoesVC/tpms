<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * One row per privileged route, checked against every role.
 *
 * This is the regression net for the authorization refactor: authorization in
 * this application lives in controller method bodies rather than in the route
 * definitions, so a forgotten check is invisible. That is how an unthrottled
 * second credential path (audit F-01) survived review.
 *
 * Adding a privileged route means adding a row here.
 */
class AuthorizationMatrixTest extends TestCase
{
    use RefreshDatabase;

    private const ALL_ROLES = ['visitor', 'hotel_manager', 'ferry_operator', 'themepark_staff', 'admin'];

    /** @return array<string, array{string, string, list<string>}> */
    public static function privilegedRoutes(): array
    {
        return [
            // key => [method, uri, roles allowed]
            'admin stats' => ['GET', '/api/admin/stats', ['admin']],
            'admin user list' => ['GET', '/api/admin/users', ['admin']],
            'admin user create' => ['POST', '/api/admin/users', ['admin']],
            'map manage' => ['GET', '/api/map/locations/manage', ['admin']],
            'map create' => ['POST', '/api/map/locations', ['admin']],
            'hotel create' => ['POST', '/api/hotels', ['hotel_manager', 'admin']],
            'ferry create' => ['POST', '/api/ferries', ['ferry_operator', 'admin']],
            'ferry schedule create' => ['POST', '/api/ferry/schedules', ['ferry_operator', 'admin']],
            'ferry templates list' => ['GET', '/api/ferry/schedule-templates', ['ferry_operator', 'admin']],
            'ferry walkup ticket' => ['POST', '/api/ferry/tickets/walkup', ['ferry_operator', 'admin']],
            'park event create' => ['POST', '/api/themepark/events', ['themepark_staff', 'admin']],
            'park slot templates list' => ['GET', '/api/themepark/slot-templates', ['themepark_staff', 'admin']],
            'park sell ticket' => ['POST', '/api/themepark/tickets/sell', ['themepark_staff', 'admin']],
            'park sales report' => ['GET', '/api/themepark/reports/sales', ['themepark_staff', 'admin']],
            'park capacity' => ['GET', '/api/themepark/capacity', ['themepark_staff', 'admin']],
            'park all slots' => ['GET', '/api/themepark/slots', ['themepark_staff', 'admin']],
            'promotions manage' => ['GET', '/api/promotions/manage',
                ['hotel_manager', 'themepark_staff', 'ferry_operator', 'admin']],
        ];
    }

    /**
     * A permitted role may still get 422 for an empty body - that is validation,
     * not authorization, so the allowed case asserts "not 403" rather than 2xx.
     */
    #[DataProvider('privilegedRoutes')]
    public function test_route_is_gated_for_every_role(string $method, string $uri, array $allowed): void
    {
        foreach (self::ALL_ROLES as $role) {
            $user = User::factory()->create()->assignRole($role);

            $status = $this->actingAs($user)->json($method, $uri)->status();

            if (in_array($role, $allowed, true)) {
                $this->assertNotSame(403, $status,
                    "{$role} SHOULD reach {$method} {$uri} but got 403");
            } else {
                $this->assertSame(403, $status,
                    "{$role} MUST NOT reach {$method} {$uri} but got {$status}");
            }
        }
    }

    #[DataProvider('privilegedRoutes')]
    public function test_route_rejects_anonymous_callers(string $method, string $uri, array $allowed): void
    {
        $this->assertContains(
            $this->json($method, $uri)->status(),
            [401, 403, 302],
            "anonymous MUST NOT reach {$method} {$uri}"
        );
    }
}

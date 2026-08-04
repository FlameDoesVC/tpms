<?php

namespace Tests\Feature\Security;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Audit finding F-12: the application sent no security response headers at all.
 */
class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_html_responses_carry_the_baseline_headers(): void
    {
        $response = $this->get('/');

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $this->assertStringContainsString('camera=(self)',
            $response->headers->get('Permissions-Policy') ?? '');
    }

    public function test_html_responses_carry_a_content_security_policy(): void
    {
        $csp = $this->get('/')->headers->get('Content-Security-Policy');

        $this->assertNotNull($csp, 'F-12: no Content-Security-Policy header.');
        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);

        // The inline theme bootstrap must run via a nonce, never 'unsafe-inline'.
        $this->assertStringContainsString('nonce-', $csp);
        $this->assertStringNotContainsString("script-src 'self' 'unsafe-inline'", $csp);
    }

    public function test_json_responses_carry_transport_headers(): void
    {
        $response = $this->getJson('/api/promotions');

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertNull($response->headers->get('Content-Security-Policy'),
            'a CSP on a JSON payload accomplishes nothing and should be omitted');
    }

    public function test_cors_is_not_wildcarded(): void
    {
        $acao = $this->getJson('/api/promotions', ['Origin' => 'https://evil.example'])
            ->headers->get('Access-Control-Allow-Origin');

        $this->assertNotSame('*', $acao,
            'F-17: every API response allowed any origin.');
    }
}

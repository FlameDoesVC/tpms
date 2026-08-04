<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Baseline security response headers. The app previously sent none of these, so
 * there was no defence-in-depth behind the (currently clean) XSS surface, the
 * admin screens were framable, and uploaded images were MIME-sniffable.
 */
class SecurityHeaders
{
    /**
     * Map tile hosts the island map genuinely needs. Kept named so the reason
     * each one is allowed stays attached to it.
     */
    private const TILE_HOSTS = [
        'https://*.tile.openstreetmap.org',   // street layer
        'https://server.arcgisonline.com',    // satellite layer
        'https://*.basemaps.cartocdn.com',    // Leaflet fallback basemaps
    ];

    public function handle(Request $request, Closure $next): Response
    {
        // Generated before the response is built so the view can read it. Laravel's
        // Vite helper applies it to every tag it emits, including the inline
        // prefetch script that Vite::prefetch() adds in AppServiceProvider.
        $nonce = Vite::useCspNonce();

        $response = $next($request);

        $response->headers->add([
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            // camera=(self) is required - both gate scanners use getUserMedia.
            'Permissions-Policy' => 'geolocation=(), microphone=(), camera=(self)',
        ]);

        // A CSP on a JSON payload accomplishes nothing; nosniff above is what
        // actually protects an API response body.
        if ($this->servesHtml($response)) {
            $response->headers->set('Content-Security-Policy', $this->policy($nonce));
        }

        return $response;
    }

    private function servesHtml(Response $response): bool
    {
        return str_contains((string) $response->headers->get('Content-Type'), 'text/html');
    }

    private function policy(string $nonce): string
    {
        $script = ["'self'", "'nonce-{$nonce}'"];
        $connect = ["'self'"];

        // Vite serves its client and HMR socket from a separate origin in dev, so
        // a strict policy would break every local page load. Production keeps the
        // tight policy because assets are built and served from this origin.
        if (! app()->isProduction()) {
            $devHosts = ['http://localhost:*', 'http://127.0.0.1:*'];
            $script = [...$script, ...$devHosts];
            $connect = [...$connect, ...$devHosts, 'ws://localhost:*', 'ws://127.0.0.1:*'];
        }

        return implode('; ', [
            "default-src 'self'",
            'script-src '.implode(' ', $script),
            // Vue scoped styles and Leaflet both write inline style attributes.
            "style-src 'self' 'unsafe-inline' https://fonts.bunny.net",
            "font-src 'self' data: https://fonts.bunny.net",
            // blob: covers the QR codes the SPA renders to canvas and downloads.
            'img-src '.implode(' ', ["'self'", 'data:', 'blob:', ...self::TILE_HOSTS]),
            'connect-src '.implode(' ', $connect),
            "media-src 'self' blob:",
            "worker-src 'self' blob:",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
        ]);
    }
}

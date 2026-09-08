<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

class AddDefensiveHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = Vite::useCspNonce();
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        if (str_starts_with((string) $response->headers->get('Content-Type'), 'text/html')) {
            $response->headers->set('Content-Security-Policy', $this->contentSecurityPolicy($nonce));
        }

        return $response;
    }

    private function contentSecurityPolicy(string $nonce): string
    {
        $scriptSources = ["'self'", "'nonce-{$nonce}'"];
        $connectSources = ["'self'"];

        if (Vite::isRunningHot()) {
            $hotUrl = trim((string) file_get_contents(Vite::hotFile()));
            $origin = parse_url($hotUrl);

            if (isset($origin['scheme'], $origin['host'])) {
                $port = isset($origin['port']) ? ':'.$origin['port'] : '';
                $httpOrigin = $origin['scheme'].'://'.$origin['host'].$port;
                $webSocketScheme = $origin['scheme'] === 'https' ? 'wss' : 'ws';

                $scriptSources[] = $httpOrigin;
                $connectSources[] = $httpOrigin;
                $connectSources[] = $webSocketScheme.'://'.$origin['host'].$port;
            }
        }

        $directives = [
            "default-src 'self'",
            'script-src '.implode(' ', $scriptSources),
            "style-src 'self' 'unsafe-inline' https://fonts.bunny.net https://fonts.googleapis.com",
            "font-src 'self' data: https://fonts.bunny.net https://fonts.gstatic.com",
            "img-src 'self' data: blob: https: http:",
            "media-src 'self' blob: https: http:",
            'connect-src '.implode(' ', $connectSources),
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'self'",
        ];

        if (app()->isProduction()) {
            $directives[] = 'upgrade-insecure-requests';
        }

        return implode('; ', $directives);
    }
}

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Browser security headers on every web response.
 *  - X-Frame-Options:        the app cannot be embedded in another site (clickjacking)
 *  - X-Content-Type-Options: browsers must not guess file types (MIME sniffing)
 *  - Referrer-Policy:        full URLs are not leaked to other sites
 *  - Permissions-Policy:     camera / microphone / location are never needed
 *  - Strict-Transport-Security: HTTPS only (sent only when the site runs on HTTPS)
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $headers = $response->headers;
        $headers->set('X-Frame-Options', 'SAMEORIGIN');
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');
        $headers->set('Cross-Origin-Opener-Policy', 'same-origin');

        if ($request->isSecure()) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}

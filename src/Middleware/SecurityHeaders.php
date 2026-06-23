<?php
/**
 * SecurityHeaders — adds defense-in-depth HTTP headers to every response.
 *
 * These headers cost nothing and stop a wide range of mischief at the
 * browser side. Tune to your situation (e.g. relax CSP if you load fonts
 * from a CDN).
 */

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class SecurityHeaders implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);
        return $response
            ->withHeader('X-Content-Type-Options',     'nosniff')
            ->withHeader('X-Frame-Options',            'DENY')
            ->withHeader('Referrer-Policy',            'no-referrer-when-downgrade')
            ->withHeader('Permissions-Policy',         'camera=(), microphone=(), geolocation=()')
            ->withHeader('Strict-Transport-Security',  'max-age=63072000; includeSubDomains')
            ->withHeader('Content-Security-Policy',    "default-src 'self'; frame-ancestors 'none'");
    }
}

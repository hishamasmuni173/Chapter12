<?php
/**
 * Cors — locks responses to a known list of origins (from CORS_ALLOWED_ORIGINS).
 *
 * If the env var is empty, falls back to '*' (no credentials).
 */

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response as SlimResponse;

final class Cors implements MiddlewareInterface
{
    /** @var string[] */
    private array $allowed;

    public function __construct()
    {
        $list = (string)($_ENV['CORS_ALLOWED_ORIGINS'] ?? '');
        $this->allowed = array_values(array_filter(array_map('trim', explode(',', $list))));
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($request->getMethod() === 'OPTIONS') {
            return $this->withCors($request, new SlimResponse(204));
        }
        return $this->withCors($request, $handler->handle($request));
    }

    private function withCors(ServerRequestInterface $req, ResponseInterface $res): ResponseInterface
    {
        $origin = $req->getHeaderLine('Origin');
        $allowOrigin = '*';
        $useCredentials = false;

        if ($this->allowed !== [] && in_array($origin, $this->allowed, true)) {
            $allowOrigin    = $origin;
            $useCredentials = true;
        }

        $res = $res
            ->withHeader('Access-Control-Allow-Origin',  $allowOrigin)
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization')
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
            ->withHeader('Vary', 'Origin');

        if ($useCredentials) {
            $res = $res->withHeader('Access-Control-Allow-Credentials', 'true');
        }
        return $res;
    }
}

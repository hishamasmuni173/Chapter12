<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Auth\JwtService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response as SlimResponse;

final class AuthMiddleware implements MiddlewareInterface
{
    public function __construct(private JwtService $jwt) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $hdr = $request->getHeaderLine('Authorization');
        if (!preg_match('/^Bearer\s+(.+)$/i', $hdr, $m)) {
            return $this->unauthorised('Missing or malformed Authorization header');
        }
        try {
            $payload = $this->jwt->verify(trim($m[1]));
        } catch (\Throwable $e) {
            error_log('[Auth] ' . $e->getMessage());
            return $this->unauthorised('Invalid or expired token');
        }
        return $handler->handle($request->withAttribute('auth', $payload));
    }

    private function unauthorised(string $msg): ResponseInterface
    {
        $r = new SlimResponse(401);
        $r->getBody()->write(json_encode(['error' => $msg]));
        return $r
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('WWW-Authenticate', 'Bearer');
    }
}

<?php
/**
 * RateLimit — sliding-window per-IP limiter backed by a file-based store.
 *
 * Suitable for the Chapter 12 lab on a single Laragon machine. In
 * production, swap the store for Redis or APCu and consider per-account
 * limiting too.
 */

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response as SlimResponse;

final class RateLimit implements MiddlewareInterface
{
    private string $store;
    private int $limit;
    private int $window;

    public function __construct(int $limit, int $windowSeconds, string $bucketName = 'default')
    {
        $this->limit  = max(1, $limit);
        $this->window = max(1, $windowSeconds);
        $this->store  = sys_get_temp_dir() . DIRECTORY_SEPARATOR
                      . 'books-api-rate-' . preg_replace('/[^a-z0-9_-]+/i', '_', $bucketName) . '.json';
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $ip = $this->clientIp($request);
        $now = time();
        $data = $this->load();
        $bucket = $data[$ip] ?? ['count' => 0, 'reset' => $now + $this->window];

        // Reset if the window has rolled over.
        if ($bucket['reset'] <= $now) {
            $bucket = ['count' => 0, 'reset' => $now + $this->window];
        }
        $bucket['count']++;
        $data[$ip] = $bucket;
        $this->save($data);

        if ($bucket['count'] > $this->limit) {
            $retry = max(1, $bucket['reset'] - $now);
            $res = new SlimResponse(429);
            $res->getBody()->write(json_encode(['error' => 'Too many requests. Please try again later.']));
            return $res
                ->withHeader('Content-Type', 'application/json')
                ->withHeader('Retry-After',           (string)$retry)
                ->withHeader('X-RateLimit-Limit',     (string)$this->limit)
                ->withHeader('X-RateLimit-Remaining', '0')
                ->withHeader('X-RateLimit-Reset',     (string)$bucket['reset']);
        }

        return $handler->handle($request)
            ->withHeader('X-RateLimit-Limit',     (string)$this->limit)
            ->withHeader('X-RateLimit-Remaining', (string)max(0, $this->limit - $bucket['count']))
            ->withHeader('X-RateLimit-Reset',     (string)$bucket['reset']);
    }

    private function clientIp(ServerRequestInterface $r): string
    {
        $params = $r->getServerParams();
        return (string)($params['REMOTE_ADDR'] ?? 'unknown');
    }

    private function load(): array
    {
        if (!is_file($this->store)) return [];
        $raw = @file_get_contents($this->store) ?: '[]';
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }

    private function save(array $data): void
    {
        @file_put_contents($this->store, json_encode($data), LOCK_EX);
    }
}

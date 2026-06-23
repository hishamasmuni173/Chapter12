<?php
declare(strict_types=1);

namespace App\Auth;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

final class JwtService
{
    private string $secret;
    private string $algo  = 'HS256';
    private int    $ttl;
    private string $issuer;

    public function __construct()
    {
        $secret = $_ENV['JWT_SECRET'] ?? '';
        if ($secret === '' || str_starts_with($secret, 'change-me')) {
            throw new \RuntimeException(
                'JWT_SECRET is missing or still the placeholder. '
              . 'Generate one: php -r "echo bin2hex(random_bytes(32));"'
            );
        }
        $this->secret = $secret;
        $this->ttl    = (int)($_ENV['JWT_TTL']    ?? 3600);
        $this->issuer = (string)($_ENV['JWT_ISSUER'] ?? 'books-api');
    }

    public function issue(int $userId, array $extra = []): string
    {
        $now = time();
        return JWT::encode(array_merge([
            'iss' => $this->issuer,
            'sub' => $userId,
            'iat' => $now,
            'exp' => $now + $this->ttl,
        ], $extra), $this->secret, $this->algo);
    }

    public function verify(string $token): array
    {
        return (array)JWT::decode($token, new Key($this->secret, $this->algo));
    }

    public function ttl(): int { return $this->ttl; }
}

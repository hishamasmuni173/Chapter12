<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth\JwtService;
use App\Repositories\AuditLog;
use App\Repositories\UserRepository;
use App\Validation\Validator;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class AuthController
{
    public function __construct(
        private UserRepository $users,
        private JwtService     $jwt,
        private AuditLog       $audit,
    ) {}

    public function register(Request $req, Response $res): Response
    {
        $body = (array)($req->getParsedBody() ?? []);

        $errors = (new Validator())
            ->required('name', 'email', 'password')
            ->field('name',     Validator::nonEmptyString(150),  'name must be a non-empty string up to 150 chars')
            ->field('email',    Validator::email(),               'invalid email')
            ->field('password', fn($v) => is_string($v) && mb_strlen($v) >= 8, 'password must be at least 8 chars')
            ->validate($body);

        if ($errors) return $this->json($res, ['errors' => $errors], 400);

        if ($this->users->emailExists($body['email'])) {
            return $this->json($res, ['error' => 'Email already registered'], 409);
        }

        $hash = password_hash($body['password'], PASSWORD_DEFAULT);
        $id   = $this->users->create($body['name'], $body['email'], $hash);
        $this->audit->record($id, 'user.register', 'users:' . $id, $this->ip($req));

        return $this->json($res, [
            'message' => 'Registered',
            'user'    => $this->users->findById($id),
        ], 201);
    }

    public function login(Request $req, Response $res): Response
    {
        $body  = (array)($req->getParsedBody() ?? []);
        $email = (string)($body['email']    ?? '');
        $pass  = (string)($body['password'] ?? '');
        $user  = $this->users->findByEmail($email);

        if (!$user || !password_verify($pass, $user['password_hash'])) {
            $this->audit->record($user['id'] ?? null, 'auth.login.fail', null, $this->ip($req), 'email=' . $email);
            return $this->json($res, ['error' => 'Invalid credentials'], 401);
        }

        $token = $this->jwt->issue((int)$user['id'], [
            'role'  => $user['role'],
            'email' => $user['email'],
        ]);
        $this->audit->record((int)$user['id'], 'auth.login.success', null, $this->ip($req));

        return $this->json($res, [
            'token_type'   => 'Bearer',
            'expires_in'   => $this->jwt->ttl(),
            'access_token' => $token,
            'user' => [
                'id'    => (int)$user['id'],
                'name'  => $user['name'],
                'email' => $user['email'],
                'role'  => $user['role'],
            ],
        ]);
    }

    public function me(Request $req, Response $res): Response
    {
        $auth = (array)$req->getAttribute('auth', []);
        $user = $this->users->findById((int)($auth['sub'] ?? 0));
        return $user
            ? $this->json($res, $user)
            : $this->json($res, ['error' => 'User not found'], 404);
    }

    private function ip(Request $r): string
    {
        return (string)($r->getServerParams()['REMOTE_ADDR'] ?? '0.0.0.0');
    }

    private function json(Response $res, mixed $data, int $status = 200): Response
    {
        // JSON_HEX_TAG/JSON_HEX_AMP/JSON_HEX_APOS/JSON_HEX_QUOT escape angle
        // brackets etc. so any JSON value is safe to embed in <script> tags.
        $res->getBody()->write(json_encode(
            $data,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
            | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        ));
        return $res
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withStatus($status);
    }
}

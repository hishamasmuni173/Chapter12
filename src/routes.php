<?php
declare(strict_types=1);

use App\Auth\JwtService;
use App\Controllers\AuthController;
use App\Controllers\BookController;
use App\Database;
use App\Middleware\AuthMiddleware;
use App\Middleware\RateLimit;
use App\Repositories\AuditLog;
use App\Repositories\BookRepository;
use App\Repositories\UserRepository;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;

return function (App $app): void {

    $pdo   = Database::get();
    $jwt   = new JwtService();
    $audit = new AuditLog($pdo);

    $authMw   = new AuthMiddleware($jwt);
    $loginMw  = new RateLimit(
        (int)($_ENV['LOGIN_RATE_LIMIT']     ?? 5),
        (int)($_ENV['LOGIN_WINDOW_SECONDS'] ?? 60),
        'login'
    );

    $bookCtrl = new BookController(new BookRepository($pdo), $audit);
    $authCtrl = new AuthController(new UserRepository($pdo), $jwt, $audit);

    // Public — health
    $app->get('/', function (Request $r, Response $s) {
        $s->getBody()->write(json_encode([
            'name'    => 'Books REST API',
            'version' => '4.0.0 (hardened)',
            'security' => [
                'security_headers' => true,
                'cors_allowlist'   => array_filter(array_map('trim', explode(',', $_ENV['CORS_ALLOWED_ORIGINS'] ?? ''))),
                'rate_limit'       => '/auth/login: ' . ($_ENV['LOGIN_RATE_LIMIT'] ?? 5)
                                    . ' per ' . ($_ENV['LOGIN_WINDOW_SECONDS'] ?? 60) . 's',
                'idor_protection'  => '/api/books/{id} owner-or-admin only',
            ],
        ]));
        return $s->withHeader('Content-Type', 'application/json');
    });

    // Auth endpoints — login is rate-limited.
    $app->post('/auth/register', [$authCtrl, 'register']);
    $app->post('/auth/login',    [$authCtrl, 'login'])->add($loginMw);
    $app->get ('/auth/me',       [$authCtrl, 'me'])  ->add($authMw);

    // Book reads stay public; writes require a valid JWT.
    $app->get('/api/books',       [$bookCtrl, 'index']);
    $app->get('/api/books/{id}',  [$bookCtrl, 'show']);
    $app->group('/api/books', function ($g) use ($bookCtrl) {
        $g->post  ('',      [$bookCtrl, 'create']);
        $g->put   ('/{id}', [$bookCtrl, 'update']);   // IDOR check inside controller
        $g->delete('/{id}', [$bookCtrl, 'delete']);   // admins only inside controller
    })->add($authMw);

    $app->options('/{routes:.+}', fn(Request $r, Response $s) => $s);
};

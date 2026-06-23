<?php
/**
 * Books REST API — entry point.
 * SCSM2223 — Chapter 12: Backend Security and Best Practices.
 */

declare(strict_types=1);

use Dotenv\Dotenv;
use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';

Dotenv::createImmutable(__DIR__ . '/..')->safeLoad();

$app = AppFactory::create();

// Order matters — last added runs FIRST on the way IN.
// We want CORS pre-flight handled before anything else, but security
// headers must be added on EVERY response, so they go closest to the
// route handler (added FIRST so they wrap last).
$app->add(new App\Middleware\SecurityHeaders());
$app->add(new App\Middleware\JsonBodyParser());
$app->add(new App\Middleware\Cors());

$app->addRoutingMiddleware();
$debug = filter_var($_ENV['APP_DEBUG'] ?? 'false', FILTER_VALIDATE_BOOLEAN);
$app->addErrorMiddleware($debug, true, true);

(require __DIR__ . '/../src/routes.php')($app);

$app->run();

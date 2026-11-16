<?php

declare(strict_types=1);

/*
 * Weeb-ify API entry point:  /api.php/anime, /api.php/anime/12, /api.php/manga, ...
 *
 * Every request goes through the same middleware, in this order:
 *
 *   1. RequestId         give the request an id (X-Request-Id header, error bodies, logs)
 *   2. RequestLogger     one log line per request: method, path, status, time, format
 *   3. Cors              CORS headers for allowed origins; answers OPTIONS preflights
 *   4. ErrorHandler      any error from here down -> safe {"error", "requestId"} response
 *   5. ContentNegotiation  Accept header -> JSON / XML / YAML, otherwise 406
 *   6. Route             known resource + id? allowed method? otherwise 404 / 405
 *   7. JsonBody          POST/PUT/PATCH: Content-Type (415), size (413), valid JSON object (400)
 *   -> ResourceController  the actual create / read / update / delete
 *
 * Then ResponseWriter sends the result in the chosen format.
 * Settings (resources, allowed fields, CORS origins, log file): app/config/api.php
 * Authentication/authorization is added in Build 3.
 */

// Never print PHP errors into API responses; ErrorHandlerMiddleware logs them instead.
ini_set('display_errors', '0');

require_once __DIR__ . '/app/Api/bootstrap.php';

use App\Api\ApiRequest;
use App\Api\ApiResponse;
use App\Api\Controllers\ResourceController;
use App\Api\Logger;
use App\Api\Middleware\ContentNegotiationMiddleware;
use App\Api\Middleware\CorsMiddleware;
use App\Api\Middleware\ErrorHandlerMiddleware;
use App\Api\Middleware\JsonBodyMiddleware;
use App\Api\Middleware\RequestIdMiddleware;
use App\Api\Middleware\RequestLoggerMiddleware;
use App\Api\Middleware\RouteMiddleware;
use App\Api\Pipeline;
use App\Api\ResponseWriter;
use App\Services\PDOService;

$config = require __DIR__ . '/app/config/api.php';
$animeFilters = require __DIR__ . '/app/config/anime-filters.php';

$logger = new Logger($config['log_file']);
$request = ApiRequest::fromGlobals();

// Connects to the database only when the controller runs a query, so a missing .env
// or a database outage becomes a logged 500 instead of a die() message.
$controller = new ResourceController(
    new PDOService(),
    $config['resources'],
    $animeFilters,
    $config['items_per_page']
);

$pipeline = new Pipeline(
    [
        new RequestIdMiddleware(),
        new RequestLoggerMiddleware($logger),
        new CorsMiddleware($config['cors_origins']),
        new ErrorHandlerMiddleware($logger),
        new ContentNegotiationMiddleware(),
        new RouteMiddleware(array_keys($config['resources'])),
        new JsonBodyMiddleware($config['max_body_bytes']),
    ],
    [$controller, 'handle']
);

try {
    $response = $pipeline->handle($request);
} catch (Throwable $e) {
    // Last resort: only reached if a middleware outside ErrorHandler itself fails
    $logger->error($request->requestId, $e::class . ': ' . $e->getMessage() . ' in ' . basename($e->getFile()) . ':' . $e->getLine());
    $request->format = 'json';
    $request->mediaType = 'application/json';
    $response = ApiResponse::error(500, 'Internal server error', $request->requestId);
}

(new ResponseWriter())->send($request, $response);
exit;

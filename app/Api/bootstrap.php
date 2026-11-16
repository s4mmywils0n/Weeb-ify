<?php

declare(strict_types=1);

// Loads every class the API uses (the project has no Composer autoloader).
require_once __DIR__ . '/../Services/PDOService.php';
require_once __DIR__ . '/../Services/AnimeListService.php';

require_once __DIR__ . '/ApiException.php';
require_once __DIR__ . '/ApiRequest.php';
require_once __DIR__ . '/ApiResponse.php';
require_once __DIR__ . '/Logger.php';
require_once __DIR__ . '/Pipeline.php';
require_once __DIR__ . '/ResponseWriter.php';

require_once __DIR__ . '/Middleware/ApiMiddleware.php';
require_once __DIR__ . '/Middleware/RequestIdMiddleware.php';
require_once __DIR__ . '/Middleware/RequestLoggerMiddleware.php';
require_once __DIR__ . '/Middleware/CorsMiddleware.php';
require_once __DIR__ . '/Middleware/ErrorHandlerMiddleware.php';
require_once __DIR__ . '/Middleware/ContentNegotiationMiddleware.php';
require_once __DIR__ . '/Middleware/RouteMiddleware.php';
require_once __DIR__ . '/Middleware/JsonBodyMiddleware.php';

require_once __DIR__ . '/Controllers/ResourceController.php';

<?php

declare(strict_types=1);

namespace App\Api\Middleware;

use App\Api\ApiRequest;
use App\Api\ApiResponse;
use App\Api\Logger;

/*
 * Logs one line per request once the response is known:
 *   method, path + query, status, time taken, response format.
 * Request bodies are never logged (they could hold personal data).
 * Sits outside ErrorHandlerMiddleware, so failed requests are logged with their final status.
 */
class RequestLoggerMiddleware implements ApiMiddleware
{
    public function __construct(private Logger $logger)
    {
    }

    public function process(ApiRequest $request, callable $next): ApiResponse
    {
        $start = hrtime(true);
        $response = $next($request);
        $ms = (int) round((hrtime(true) - $start) / 1e6);

        $query = $_SERVER['QUERY_STRING'] ?? '';
        $this->logger->info($request->requestId, sprintf(
            '%s %s%s %d %dms %s',
            $request->method,
            $request->path === '' ? '/' : $request->path,
            $query !== '' ? '?' . substr($query, 0, 500) : '',
            $response->status,
            $ms,
            $request->format
        ));

        return $response;
    }
}

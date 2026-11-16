<?php

declare(strict_types=1);

namespace App\Api\Middleware;

use App\Api\ApiRequest;
use App\Api\ApiResponse;

/*
 * Gives every request an id, sent back in the X-Request-Id header and in every error body.
 * The same id is on every log line for that request, so "it failed, request id 3f2a..."
 * leads straight to the cause in storage/logs/api.log.
 *
 * A client may send its own X-Request-Id (8-64 letters, digits, '.', '_' or '-');
 * anything else is ignored and a new id is generated.
 */
class RequestIdMiddleware implements ApiMiddleware
{
    public function process(ApiRequest $request, callable $next): ApiResponse
    {
        $incoming = $request->header('X-Request-Id');
        $request->requestId = preg_match('/\A[A-Za-z0-9._-]{8,64}\z/', $incoming) === 1
            ? $incoming
            : bin2hex(random_bytes(8));

        return $next($request)->withHeader('X-Request-Id', $request->requestId);
    }
}

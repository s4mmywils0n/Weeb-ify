<?php

declare(strict_types=1);

namespace App\Api\Middleware;

use App\Api\ApiRequest;
use App\Api\ApiResponse;

/*
 * Cross-origin (CORS) headers, only for origins listed in app/config/api.php
 * (previously every site was allowed with "*").
 *
 * Same-origin requests from the app's own pages don't need any of this.
 * Answers browser preflight requests (OPTIONS) here, before the rest of the pipeline.
 * Sits outside ErrorHandlerMiddleware so error responses get the headers too,
 * otherwise the browser would hide the error body from the calling page.
 */
class CorsMiddleware implements ApiMiddleware
{
    private const EXPOSED = 'X-Request-Id, X-Next-Cursor, X-Prev-Cursor, X-Page, X-Total-Pages';

    /**
     * @param string[] $allowedOrigins e.g. ['http://localhost', 'http://127.0.0.1']
     */
    public function __construct(private array $allowedOrigins)
    {
    }

    public function process(ApiRequest $request, callable $next): ApiResponse
    {
        $origin = $request->header('Origin');
        $allowed = $origin !== '' && in_array($origin, $this->allowedOrigins, true);

        if ($request->method === 'OPTIONS') {
            $response = new ApiResponse(204);
            if ($allowed) {
                $response->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS')
                    ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Accept, X-Request-Id')
                    ->withHeader('Access-Control-Max-Age', '600');
            }
        } else {
            $response = $next($request);
        }

        $response->withHeader('Vary', 'Origin, Accept');
        if ($allowed) {
            $response->withHeader('Access-Control-Allow-Origin', $origin)
                ->withHeader('Access-Control-Expose-Headers', self::EXPOSED);
        }

        return $response;
    }
}

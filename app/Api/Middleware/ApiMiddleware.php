<?php

declare(strict_types=1);

namespace App\Api\Middleware;

use App\Api\ApiRequest;
use App\Api\ApiResponse;

/*
 * API middleware (api.php). Different from App\Middleware\Middleware, which is for
 * the HTML pages and redirects; API middleware always answers with an ApiResponse.
 *
 * To let the request continue:   return $next($request);
 * To stop it:                    throw new ApiException(400, '...');
 */
interface ApiMiddleware
{
    /**
     * @param callable(ApiRequest): ApiResponse $next
     */
    public function process(ApiRequest $request, callable $next): ApiResponse;
}

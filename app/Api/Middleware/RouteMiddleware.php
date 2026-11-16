<?php

declare(strict_types=1);

namespace App\Api\Middleware;

use App\Api\ApiException;
use App\Api\ApiRequest;
use App\Api\ApiResponse;

/*
 * Matches /{resource} and /{resource}/{id} against the resources in app/config/api.php,
 * then checks the HTTP method:
 *
 *   /anime       GET (list), POST (create)                 -> anything else: 405
 *   /anime/12    GET, PUT, PATCH, DELETE                   -> anything else: 405
 *   /unknown     404
 *
 * 405 responses include an Allow header listing the methods that do work.
 */
class RouteMiddleware implements ApiMiddleware
{
    public const COLLECTION_METHODS = ['GET', 'POST'];
    public const ITEM_METHODS = ['GET', 'PUT', 'PATCH', 'DELETE'];

    /**
     * @param string[] $resources names, e.g. ['anime', 'manga']
     */
    public function __construct(private array $resources)
    {
    }

    public function process(ApiRequest $request, callable $next): ApiResponse
    {
        $names = implode('|', array_map(fn($name) => preg_quote($name, '#'), $this->resources));

        // ids: 1 to 18 digits (always fits in a PHP int), no leading zero
        if (preg_match('#\A/(' . $names . ')(?:/([1-9][0-9]{0,17}))?/?\z#', $request->path, $m) !== 1) {
            throw new ApiException(404, 'Endpoint not found');
        }

        $request->resource = $m[1];
        $request->id = isset($m[2]) && $m[2] !== '' ? (int) $m[2] : null;

        $allowed = $request->id === null ? self::COLLECTION_METHODS : self::ITEM_METHODS;
        if (!in_array($request->method, $allowed, true)) {
            throw new ApiException(405, 'Method not allowed', ['Allow' => implode(', ', [...$allowed, 'OPTIONS'])]);
        }

        return $next($request);
    }
}

<?php

declare(strict_types=1);

namespace App\Api\Middleware;

use App\Api\ApiException;
use App\Api\ApiRequest;
use App\Api\ApiResponse;
use App\Models\AccountModel;

/*
 * Only admins may change the catalogue.
 *
 * For POST, PUT, PATCH and DELETE on the listed resources (anime, manga):
 *   not logged in               -> 401 Login required
 *   logged in, is_admin = false -> 403 Admin access required
 * Reads (GET) stay public. /favorites is not listed: it has its own per-user check.
 *
 * is_admin is read from the database on every write (not cached in the session),
 * so removing someone's admin flag takes effect immediately.
 *
 * Runs after RouteMiddleware (so $request->resource is known) and before
 * JsonBodyMiddleware (so an anonymous caller is refused before its body is read).
 */
class AdminWriteMiddleware implements ApiMiddleware
{
    private const WRITE_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    /**
     * @param string[] $resources resources whose writes need an admin, e.g. ['anime', 'manga']
     */
    public function __construct(private AccountModel $accounts, private array $resources)
    {
    }

    public function process(ApiRequest $request, callable $next): ApiResponse
    {
        if (
            !in_array($request->method, self::WRITE_METHODS, true)
            || !in_array($request->resource, $this->resources, true)
        ) {
            return $next($request);
        }

        $userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
        if ($userId === null) {
            throw new ApiException(401, 'Login required');
        }
        if (!$this->accounts->isAdmin($userId)) {
            throw new ApiException(403, 'Admin access required');
        }

        return $next($request);
    }
}

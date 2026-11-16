<?php

declare(strict_types=1);

namespace App\Api\Middleware;

use App\Api\ApiException;
use App\Api\ApiRequest;
use App\Api\ApiResponse;
use App\Api\Logger;
use ErrorException;
use PDOException;
use Throwable;

/*
 * The one place where things that go wrong become a safe error response.
 *
 *  - ApiException          -> its own status and message (written to be shown to clients)
 *  - duplicate value (DB)  -> 409, invalid value for a column (DB) -> 400
 *  - anything else         -> 500 "Internal server error"
 *
 * The client only ever gets {"error": "...", "requestId": "..."}. The real details
 * (exception class, message, file and line) go to storage/logs/api.log under the same id.
 *
 * PHP warnings and notices are turned into exceptions while the request runs, so they
 * end up here too instead of being printed into the response.
 */
class ErrorHandlerMiddleware implements ApiMiddleware
{
    public function __construct(private Logger $logger)
    {
    }

    public function process(ApiRequest $request, callable $next): ApiResponse
    {
        set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
            if (!(error_reporting() & $severity)) {
                return false; // silenced with @
            }
            throw new ErrorException($message, 0, $severity, $file, $line);
        });

        try {
            return $next($request);
        } catch (ApiException $e) {
            return ApiResponse::error($e->status, $e->getMessage(), $request->requestId, $e->headers);
        } catch (Throwable $e) {
            $this->logger->error($request->requestId, sprintf(
                '%s: %s in %s:%d',
                $e::class,
                $e->getMessage(),
                basename($e->getFile()),
                $e->getLine()
            ));

            [$status, $message] = $this->publicError($e);
            return ApiResponse::error($status, $message, $request->requestId);
        } finally {
            restore_error_handler();
        }
    }

    /** @return array{int, string} */
    private function publicError(Throwable $e): array
    {
        if ($e instanceof PDOException) {
            $sqlState = (string) ($e->errorInfo[0] ?? $e->getCode());
            if ($sqlState === '23505') {
                return [409, 'A record with one of these values already exists.'];
            }
            // Class 22 = data exception (bad date, number too large, text too long, ...)
            if (str_starts_with($sqlState, '22')) {
                return [400, 'One of the field values is not valid for its column.'];
            }
        }
        return [500, 'Internal server error'];
    }
}

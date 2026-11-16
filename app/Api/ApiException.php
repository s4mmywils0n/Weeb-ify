<?php

declare(strict_types=1);

namespace App\Api;

use RuntimeException;

/*
 * Throw this anywhere in the API to stop and answer with an error:
 *
 *   throw new ApiException(404, 'Record not found');
 *
 * ErrorHandlerMiddleware turns it into the right status code and a
 * {"error": "...", "requestId": "..."} body. The message IS shown to the client,
 * so only put safe, user-facing text in it.
 */
class ApiException extends RuntimeException
{
    /**
     * @param array<string,string> $headers extra response headers, e.g. ['Allow' => 'GET, POST']
     */
    public function __construct(
        public readonly int $status,
        string $message,
        public readonly array $headers = []
    ) {
        parent::__construct($message);
    }
}

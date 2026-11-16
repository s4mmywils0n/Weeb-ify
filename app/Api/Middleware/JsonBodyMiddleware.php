<?php

declare(strict_types=1);

namespace App\Api\Middleware;

use App\Api\ApiException;
use App\Api\ApiRequest;
use App\Api\ApiResponse;
use JsonException;
use stdClass;

/*
 * For POST, PUT and PATCH: checks and decodes the request body once, so controllers
 * just read $request->body.
 *
 *   Content-Type not application/json  -> 415 Unsupported Media Type
 *   body larger than max_body_bytes    -> 413 Payload Too Large
 *   empty body / invalid JSON          -> 400
 *   JSON that isn't an object ({...})  -> 400
 *
 * Request bodies are JSON only; XML and YAML are supported for responses.
 */
class JsonBodyMiddleware implements ApiMiddleware
{
    private const METHODS_WITH_BODY = ['POST', 'PUT', 'PATCH'];

    public function __construct(private int $maxBytes)
    {
    }

    public function process(ApiRequest $request, callable $next): ApiResponse
    {
        if (!in_array($request->method, self::METHODS_WITH_BODY, true)) {
            return $next($request);
        }

        $contentType = strtolower(trim(explode(';', $request->header('Content-Type'))[0]));
        if ($contentType !== 'application/json') {
            throw new ApiException(415, 'Unsupported media type. Send the request body as application/json.');
        }

        $length = $request->header('Content-Length');
        if ($length !== '' && ctype_digit($length) && (int) $length > $this->maxBytes) {
            throw new ApiException(413, "Request body is too large (max {$this->maxBytes} bytes).");
        }

        $raw = $request->rawBody($this->maxBytes);
        if (strlen($raw) > $this->maxBytes) {
            throw new ApiException(413, "Request body is too large (max {$this->maxBytes} bytes).");
        }
        if (trim($raw) === '') {
            throw new ApiException(400, 'Request body is required.');
        }

        try {
            // Decode as objects first, only to tell {} (object) apart from [] (list)
            $decoded = json_decode($raw, false, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new ApiException(400, 'Invalid JSON object');
        }
        if (!$decoded instanceof stdClass) {
            throw new ApiException(400, 'Invalid JSON object');
        }

        $request->body = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);

        return $next($request);
    }
}

<?php

declare(strict_types=1);

namespace App\Api;

/*
 * Everything the API needs to know about the incoming request.
 * Middleware fills in the later fields as the request passes through:
 *   requestId  - RequestIdMiddleware
 *   format     - ContentNegotiationMiddleware ('json' | 'xml' | 'yaml')
 *   resource/id - RouteMiddleware
 *   body       - JsonBodyMiddleware (decoded JSON object for POST/PUT/PATCH)
 */
class ApiRequest
{
    public string $requestId = '';
    public string $format = 'json';
    public string $mediaType = 'application/json';
    public ?string $resource = null;
    public ?int $id = null;
    public array $body = [];

    /**
     * @param array<string,string> $headers header names in lower case
     */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $query,
        public readonly array $headers
    ) {
    }

    public static function fromGlobals(): self
    {
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

        // Base path: /Weeb-ify/api.php under Apache, /api.php under `php -S`
        $position = strpos($uri, '/api.php');
        $path = $position === false ? '' : substr($uri, $position + strlen('/api.php'));

        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $headers[strtolower(str_replace('_', '-', substr($key, 5)))] = (string) $value;
            }
        }
        // PHP keeps these two outside HTTP_*
        foreach (['CONTENT_TYPE' => 'content-type', 'CONTENT_LENGTH' => 'content-length'] as $key => $name) {
            if (isset($_SERVER[$key]) && $_SERVER[$key] !== '') {
                $headers[$name] = (string) $_SERVER[$key];
            }
        }

        return new self(
            strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'),
            $path,
            $_GET,
            $headers
        );
    }

    public function header(string $name): string
    {
        return $this->headers[strtolower($name)] ?? '';
    }

    // Read the raw body (only called by JsonBodyMiddleware). At most $maxBytes + 1 bytes are read.
    public function rawBody(int $maxBytes): string
    {
        $body = file_get_contents('php://input', false, null, 0, $maxBytes + 1);
        return $body === false ? '' : $body;
    }
}

<?php

declare(strict_types=1);

namespace App\Api;

/*
 * What the API sends back. Controllers return one of these instead of echoing,
 * so middleware can still add headers (request id, CORS, ...) on the way out.
 * ResponseWriter turns $data into JSON, XML or YAML at the very end.
 */
class ApiResponse
{
    /**
     * @param array<string,string> $headers
     */
    public function __construct(
        public int $status = 200,
        public mixed $data = null,
        public array $headers = []
    ) {
    }

    public static function error(int $status, string $message, string $requestId, array $headers = []): self
    {
        return new self($status, ['error' => $message, 'requestId' => $requestId], $headers);
    }

    public function withHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }
}

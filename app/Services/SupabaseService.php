<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

class SupabaseService
{
    private string $url;
    private string $key;

    public function __construct(?string $url = null, ?string $key = null)
    {
        $envPath = __DIR__ . '/../.env';
        $fileEnv = is_file($envPath) ? parse_ini_file($envPath) : [];
        if ($fileEnv === false) {
            throw new RuntimeException('Unable to read the application environment file.');
        }

        if ($url === null || $url === '') {
            $envUrl = $_ENV['SUPABASE_URL'] ?? getenv('SUPABASE_URL');
            $url = is_string($envUrl) && $envUrl !== '' ? $envUrl : ($fileEnv['SUPABASE_URL'] ?? null);
        }

        if ($key === null || $key === '') {
            $envKey = $_ENV['SUPABASE_SERVICE_ROLE_KEY'] ?? getenv('SUPABASE_SERVICE_ROLE_KEY');
            $key = is_string($envKey) && $envKey !== '' ? $envKey : ($fileEnv['SUPABASE_SERVICE_ROLE_KEY'] ?? null);
        }

        if (!is_string($url) || $url === '') {
            throw new RuntimeException('SUPABASE_URL is required.');
        }

        if (!is_string($key) || $key === '') {
            throw new RuntimeException('SUPABASE_SERVICE_ROLE_KEY is required.');
        }

        $this->url = rtrim($url, '/');
        $this->key = $key;
    }

    public function insert(string $table, array $data): array
    {
        return $this->request('POST', $table, [], $data);
    }

    public function select(string $table, array $filters = [], array $columns = ['*']): array
    {
        $query = ['select' => implode(',', $columns)];
        foreach ($filters as $column => $value) {
            $query[$column] = 'eq.' . $value;
        }

        return $this->request('GET', $table, $query);
    }

    public function update(string $table, array $data, array $filters): array
    {
        if ($filters === []) {
            throw new RuntimeException('Supabase updates require at least one filter.');
        }

        $query = [];
        foreach ($filters as $column => $value) {
            $query[$column] = 'eq.' . $value;
        }

        return $this->request('PATCH', $table, $query, $data);
    }

    public function delete(string $table, array $filters): array
    {
        if ($filters === []) {
            throw new RuntimeException('Supabase deletes require at least one filter.');
        }

        $query = [];
        foreach ($filters as $column => $value) {
            $query[$column] = 'eq.' . $value;
        }

        return $this->request('DELETE', $table, $query);
    }

    private function request(string $method, string $table, array $query = [], ?array $data = null): array
    {
        $url = $this->url . '/rest/v1/' . rawurlencode($table);
        if ($query !== []) {
            $url .= '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        }

        $headers = [
            'apikey: ' . $this->key,
            'Authorization: Bearer ' . $this->key,
            'Accept: application/json',
            'Prefer: return=representation',
        ];
        $options = [
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", $headers),
                'ignore_errors' => true,
                'timeout' => 15,
            ],
        ];

        if ($data !== null) {
            $headers[] = 'Content-Type: application/json';
            $options['http']['header'] = implode("\r\n", $headers);
            $options['http']['content'] = json_encode($data, JSON_THROW_ON_ERROR);
        }

        $response = file_get_contents($url, false, stream_context_create($options));
        if ($response === false) {
            $lastError = error_get_last();
            throw new RuntimeException('Supabase request failed: ' . ($lastError['message'] ?? 'No response received.'));
        }

        $statusLine = $http_response_header[0] ?? '';
        preg_match('/\s(\d{3})\s/', $statusLine, $statusMatch);
        $status = isset($statusMatch[1]) ? (int) $statusMatch[1] : 0;

        if ($status < 200 || $status >= 300) {
            $error = json_decode($response, true);
            $message = is_array($error)
                ? ($error['message'] ?? $error['error_description'] ?? $response)
                : $response;
            throw new RuntimeException(sprintf('Supabase request failed (%d): %s', $status, $message));
        }

        if ($response === '') {
            return [];
        }

        $result = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($result)) {
            throw new RuntimeException('Supabase returned an unexpected response.');
        }

        return $result;
    }
}

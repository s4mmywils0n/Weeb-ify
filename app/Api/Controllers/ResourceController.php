<?php

declare(strict_types=1);

namespace App\Api\Controllers;

use App\Api\ApiException;
use App\Api\ApiRequest;
use App\Api\ApiResponse;
use App\Services\AnimeListService;
use App\Services\PDOService;
use InvalidArgumentException;
use PDO;

/*
 * Create / read / update / delete for the resources in app/config/api.php.
 * (This is the logic that used to be written inline in api.php.)
 *
 * By the time a method here runs, the middleware has already:
 *   - matched the route and checked the HTTP method   ($request->resource, $request->id)
 *   - decoded and checked the JSON body               ($request->body)
 * Errors are thrown as ApiException; anything unexpected is turned into a safe
 * 500 by ErrorHandlerMiddleware.
 *
 * SQL safety: values are always bound parameters. Table and column names only come
 * from the config allowlist and are double-quoted, which also makes reserved
 * words ("user") and camelCase names work.
 */
class ResourceController
{
    public function __construct(
        private PDOService $db,
        private array $resources,
        private array $animeFilters,
        private int $itemsPerPage
    ) {
    }

    public function handle(ApiRequest $request): ApiResponse
    {
        if ($request->id === null) {
            return $request->method === 'POST' ? $this->create($request) : $this->list($request);
        }

        return match ($request->method) {
            'GET'    => $this->show($request),
            'DELETE' => $this->delete($request),
            default  => $this->update($request), // PUT or PATCH (RouteMiddleware allows nothing else)
        };
    }

    // GET /anime, GET /manga
    private function list(ApiRequest $request): ApiResponse
    {
        // Anime: filters, categories, sorting and cursor pagination (see AnimeListService).
        // Cursors go in headers so the body stays a plain list.
        if ($request->resource === 'anime') {
            try {
                $result = (new AnimeListService($this->pdo(), $this->animeFilters))->list($request->query);
            } catch (InvalidArgumentException $e) {
                throw new ApiException(400, $e->getMessage());
            }

            $response = new ApiResponse(200, $result['records']);
            $response->withHeader('X-Total-Pages', (string) $result['pages']);
            if ($result['page'] !== null) {
                $response->withHeader('X-Page', (string) $result['page']);
            }
            if ($result['next'] !== null) {
                $response->withHeader('X-Next-Cursor', $result['next']);
            }
            if ($result['prev'] !== null) {
                $response->withHeader('X-Prev-Cursor', $result['prev']);
            }
            return $response;
        }

        // Everything else: ?page=N, fixed page size, ordered by id
        $rawPage = $request->query['page'] ?? '1';
        $page = is_string($rawPage)
            ? filter_var($rawPage, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000000]])
            : false; // ?page[]=1
        if ($page === false) {
            throw new ApiException(400, 'The page parameter must be an integer between 1 and 1000000');
        }

        $stmt = $this->pdo()->prepare(
            'SELECT * FROM public.' . $this->table($request) . ' ORDER BY id ASC LIMIT :limit OFFSET :offset'
        );
        $stmt->bindValue(':limit', $this->itemsPerPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', ($page - 1) * $this->itemsPerPage, PDO::PARAM_INT);
        $stmt->execute();

        return new ApiResponse(200, $stmt->fetchAll());
    }

    // GET /anime/12
    private function show(ApiRequest $request): ApiResponse
    {
        return new ApiResponse(200, $this->findOrFail($request));
    }

    // POST /anime
    private function create(ApiRequest $request): ApiResponse
    {
        $input = $request->body;
        $this->validate($request, $input, requireAll: true);

        $fields = array_keys($input);
        $columns = implode(', ', array_map([$this, 'quote'], $fields));
        $placeholders = implode(', ', array_map(fn(int $i) => ":p$i", array_keys($fields)));

        $stmt = $this->pdo()->prepare(
            'INSERT INTO public.' . $this->table($request) . " ($columns) VALUES ($placeholders) RETURNING *"
        );
        $this->bindValues($stmt, array_values($input));
        $stmt->execute();

        return new ApiResponse(201, $stmt->fetch());
    }

    // PUT /anime/12 (replace: missing fields become NULL) and PATCH /anime/12 (only the sent fields)
    private function update(ApiRequest $request): ApiResponse
    {
        $this->findOrFail($request);

        $input = $request->body;
        $isPut = $request->method === 'PUT';
        $this->validate($request, $input, requireAll: $isPut);

        if ($isPut) {
            foreach ($this->resources[$request->resource]['fields'] as $field) {
                if (!array_key_exists($field, $input)) {
                    $input[$field] = null;
                }
            }
        }

        if ($input === []) {
            throw new ApiException(400, 'No fields provided');
        }

        $fields = array_keys($input);
        $set = implode(', ', array_map(fn(int $i, string $field) => $this->quote($field) . " = :p$i", array_keys($fields), $fields));

        $stmt = $this->pdo()->prepare(
            'UPDATE public.' . $this->table($request) . " SET $set WHERE id = :id RETURNING *"
        );
        $this->bindValues($stmt, array_values($input));
        $stmt->bindValue(':id', $request->id, PDO::PARAM_INT);
        $stmt->execute();

        return new ApiResponse(200, $stmt->fetch());
    }

    // DELETE /anime/12
    private function delete(ApiRequest $request): ApiResponse
    {
        $stmt = $this->pdo()->prepare('DELETE FROM public.' . $this->table($request) . ' WHERE id = :id');
        $stmt->bindValue(':id', $request->id, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() === 0) {
            throw new ApiException(404, 'Record not found');
        }

        return new ApiResponse(200, ['message' => 'Record deleted successfully']);
    }

    // ------------------------------------------------------------------ helpers

    private function findOrFail(ApiRequest $request): array
    {
        $stmt = $this->pdo()->prepare('SELECT * FROM public.' . $this->table($request) . ' WHERE id = :id');
        $stmt->bindValue(':id', $request->id, PDO::PARAM_INT);
        $stmt->execute();
        $record = $stmt->fetch();

        if (!$record) {
            throw new ApiException(404, 'Record not found');
        }
        return $record;
    }

    /**
     * Unknown fields, value types and required fields.
     * $requireAll: POST and PUT need every required field; PATCH only checks the ones sent.
     */
    private function validate(ApiRequest $request, array $input, bool $requireAll): void
    {
        $config = $this->resources[$request->resource];

        $unknown = array_diff(array_keys($input), $config['fields']);
        if ($unknown !== []) {
            throw new ApiException(400, 'Unknown field provided: ' . implode(', ', array_slice($unknown, 0, 10))
                . '. Allowed: ' . implode(', ', $config['fields']));
        }

        foreach ($input as $field => $value) {
            if (is_array($value)) {
                throw new ApiException(400, "Field \"$field\" must be a string, number, boolean or null.");
            }
        }

        foreach ($config['required'] as $field) {
            if (!$requireAll && !array_key_exists($field, $input)) {
                continue;
            }
            $value = $input[$field] ?? null;
            if (!is_string($value) || trim($value) === '') {
                throw new ApiException(400, "A non-empty $field is required");
            }
        }
    }

    private function bindValues(\PDOStatement $stmt, array $values): void
    {
        foreach ($values as $i => $value) {
            $type = match (true) {
                $value === null  => PDO::PARAM_NULL,
                is_bool($value)  => PDO::PARAM_BOOL,
                is_int($value)   => PDO::PARAM_INT,
                default          => PDO::PARAM_STR,
            };
            $stmt->bindValue(":p$i", is_float($value) ? (string) $value : $value, $type);
        }
    }

    private function table(ApiRequest $request): string
    {
        return $this->quote($this->resources[$request->resource]['table']);
    }

    // show_type -> "show_type" (identifier quoting in Postgres)
    private function quote(string $identifier): string
    {
        return '"' . str_replace('"', '""', $identifier) . '"';
    }

    private function pdo(): PDO
    {
        return $this->db->getPDO();
    }
}

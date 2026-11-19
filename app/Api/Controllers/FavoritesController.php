<?php

declare(strict_types=1);

namespace App\Api\Controllers;

use App\Api\ApiException;
use App\Api\ApiRequest;
use App\Api\ApiResponse;
use App\Services\PDOService;
use PDO;
use PDOException;

/*
 * The logged-in user's favorites (ported from the old single-file api.php on main).
 *
 *   GET    /favorites                    the user's anime favorites (newest first)
 *   GET    /favorites?type=manga         the user's manga favorites
 *   GET    /favorites?anime_id=12        {"isFavorite": true|false}   (or ?manga_id=)
 *   POST   /favorites   {"anime_id": 12} add it      (or {"manga_id": 12})  - idempotent
 *   DELETE /favorites?anime_id=12        remove it   (or ?manga_id=)        - 404 if it wasn't one
 *
 * Every query is scoped to $_SESSION['user_id'], so nobody can read or change
 * another user's favorites. No session -> 401.
 */
class FavoritesController
{
    // id = the anime/manga row id (the favorites page links to /anime/{id} with it).
    private const TARGETS = [
        'anime' => [
            'column' => 'anime_id',
            'table'  => 'public.anime',
            'alias'  => 'a',
            'detail' => 'a.id, a.title, a.image, a.episode_count, a.show_type, '
                      . 'a.age_rating, a.popularity_rank, a.status',
        ],
        'manga' => [
            'column' => 'manga_id',
            'table'  => 'public.manga',
            'alias'  => 'm',
            'detail' => 'm.id, m.title, m.image, m.chapter_count, m.volume_count, '
                      . 'm.manga_type, m.serialization, m.status',
        ],
    ];

    public function __construct(private PDOService $db)
    {
    }

    public function handle(ApiRequest $request): ApiResponse
    {
        $userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
        if ($userId === null) {
            throw new ApiException(401, 'Login required');
        }

        $type = $this->type($request);
        $target = self::TARGETS[$type];
        $column = $target['column'];
        $targetId = $this->targetId($request, $column);

        return match ($request->method) {
            'GET'    => $targetId !== null
                ? $this->isFavorite($userId, $column, $targetId)
                : $this->list($userId, $target),
            'POST'   => $this->add($userId, $column, $this->requireId($targetId, $column)),
            'DELETE' => $this->remove($userId, $column, $this->requireId($targetId, $column)),
        };
    }

    private function list(int $userId, array $target): ApiResponse
    {
        $column = $target['column'];
        $alias = $target['alias'];
        // LEFT JOIN: a favorite still shows if its anime/manga row is missing.
        // IS NOT NULL: anime and manga favorites share the table; keep only this kind.
        $stmt = $this->pdo()->prepare(
            "SELECT f.$column, f.created_at, {$target['detail']}
               FROM public.favorites f
               LEFT JOIN {$target['table']} $alias ON $alias.$column = f.$column
              WHERE f.user_id = :user_id AND f.$column IS NOT NULL
              ORDER BY f.created_at DESC"
        );
        $stmt->execute([':user_id' => $userId]);
        return new ApiResponse(200, $stmt->fetchAll());
    }

    private function isFavorite(int $userId, string $column, int $targetId): ApiResponse
    {
        $stmt = $this->pdo()->prepare(
            "SELECT 1 FROM public.favorites WHERE user_id = :user_id AND $column = :target_id LIMIT 1"
        );
        $stmt->execute([':user_id' => $userId, ':target_id' => $targetId]);
        return new ApiResponse(200, ['isFavorite' => $stmt->fetchColumn() !== false]);
    }

    private function add(int $userId, string $column, int $targetId): ApiResponse
    {
        try {
            // ON CONFLICT DO NOTHING: a double click is not an error
            $stmt = $this->pdo()->prepare(
                "INSERT INTO public.favorites (user_id, $column) VALUES (:user_id, :target_id)
                 ON CONFLICT DO NOTHING"
            );
            $stmt->execute([':user_id' => $userId, ':target_id' => $targetId]);
        } catch (PDOException $e) {
            $type = $column === 'manga_id' ? 'manga' : 'anime';
            match ((string) $e->getCode()) {
                '23503' => throw new ApiException(404, "That $type does not exist."),            // foreign key
                '23505' => throw new ApiException(409, "That $type is already in your favorites."), // unique
                '23514' => throw new ApiException(400, "That $type could not be favorited."),      // CHECK
                // Anything else (e.g. a missing column) is a server problem: rethrow so
                // ErrorHandlerMiddleware logs the details under the request id and the
                // client only gets a safe 500 (no SQL state or message).
                default => throw $e,
            };
        }
        return new ApiResponse(201, ['success' => true]);
    }

    private function remove(int $userId, string $column, int $targetId): ApiResponse
    {
        $stmt = $this->pdo()->prepare(
            "DELETE FROM public.favorites WHERE user_id = :user_id AND $column = :target_id"
        );
        $stmt->execute([':user_id' => $userId, ':target_id' => $targetId]);

        if ($stmt->rowCount() === 0) {
            throw new ApiException(404, 'Favorite not found');
        }
        return new ApiResponse(200, ['success' => true]);
    }

    // ?type=anime|manga; without it, a manga_id in the query/body means manga
    private function type(ApiRequest $request): string
    {
        $raw = $request->query['type'] ?? '';
        $type = is_string($raw) ? strtolower(trim($raw)) : '';
        if ($type === '') {
            $type = array_key_exists('manga_id', $request->query) || array_key_exists('manga_id', $request->body)
                ? 'manga'
                : 'anime';
        }
        if (!isset(self::TARGETS[$type])) {
            throw new ApiException(400, 'Invalid type. Allowed: anime, manga');
        }
        return $type;
    }

    private function targetId(ApiRequest $request, string $column): ?int
    {
        $raw = $request->query[$column] ?? $request->body[$column] ?? null;
        if ($raw === null) {
            return null;
        }
        $id = filter_var($raw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) {
            throw new ApiException(400, "A valid $column is required");
        }
        return (int) $id;
    }

    private function requireId(?int $targetId, string $column): int
    {
        if ($targetId === null) {
            throw new ApiException(400, "A valid $column is required");
        }
        return $targetId;
    }

    private function pdo(): PDO
    {
        return $this->db->getPDO();
    }
}

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
 *   DELETE /favorites?anime_id=12        remove it   (or ?manga_id=)        - idempotent
 *
 * Every query is scoped to $_SESSION['user_id'], so nobody can read or change
 * another user's favorites. No session -> 401.
 */
class FavoritesController
{
    private const TARGETS = [
        'anime' => [
            'column' => 'anime_id',
            'table'  => 'public.anime',
            'alias'  => 'a',
            'detail' => 'a.id, a.title, a.image, a.episode_count, a.status, a.show_type, '
                      . 'a.age_rating, a.popularity_rank',
        ],
        'manga' => [
            'column' => 'manga_id',
            'table'  => 'public.manga',
            'alias'  => 'm',
            'detail' => 'm.id, m.title, m.image, m.chaptercount AS chapter_count, '
                      . 'm.volumecount AS volume_count, m.mangatype AS manga_type, '
                      . 'm.serialization, m.status, m.agerating AS age_rating, '
                      . 'm.popularityrank AS popularity_rank',
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
        $stmt = $this->pdo()->prepare(
            "SELECT f.$column, {$target['detail']}
               FROM public.favorites f
               JOIN {$target['table']} $alias ON $alias.$column = f.$column
              WHERE f.user_id = :user_id
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
        } catch (PDOException) {
            // Unknown id (foreign key) or CHECK constraint -> 400 rather than 500.
            // ErrorHandlerMiddleware is not involved, so log nothing sensitive here.
            throw new ApiException(400, "That $column could not be favorited.");
        }
        return new ApiResponse(201, ['success' => true]);
    }

    private function remove(int $userId, string $column, int $targetId): ApiResponse
    {
        $stmt = $this->pdo()->prepare(
            "DELETE FROM public.favorites WHERE user_id = :user_id AND $column = :target_id"
        );
        $stmt->execute([':user_id' => $userId, ':target_id' => $targetId]);
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

<?php

declare(strict_types=1);

namespace App\Middleware;

/*
 * The {user} in the URL must be the logged-in user.
 * Anyone opening someone else's page (e.g. /profile/2/favorites) is sent to their own.
 *
 * Always list it AFTER AuthMiddleware, which makes sure someone is logged in.
 */
class OwnsProfileMiddleware implements Middleware
{
    public function handle(array $params): void
    {
        $userId = currentUserId();
        $requested = filter_var($params['user'] ?? null, FILTER_VALIDATE_INT);

        if ($userId !== null && $requested !== $userId) {
            redirect('/profile/' . $userId . '/favorites');
        }
    }
}

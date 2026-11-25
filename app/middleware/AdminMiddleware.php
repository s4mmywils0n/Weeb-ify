<?php

declare(strict_types=1);

namespace App\Middleware;

/*
 * Only admins (user.is_admin = true) get through.
 * List it AFTER AuthMiddleware, which sends logged-out visitors to /login first.
 * A logged-in non-admin gets a 403 page.
 *
 * This only hides the admin page. The real protection is in the API
 * (the admin check in api.php), which refuses every anime/manga write from a non-admin.
 */
class AdminMiddleware implements Middleware
{
    public function handle(array $params): void
    {
        if (!currentUserIsAdmin()) {
            http_response_code(403);
            view('forbidden');
            exit;
        }
    }
}

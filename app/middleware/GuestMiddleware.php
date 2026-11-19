<?php

declare(strict_types=1);

namespace App\Middleware;

/*
 * Only logged-OUT users get through (log in / sign up pages).
 * Someone who is already logged in is sent to the main page.
 */
class GuestMiddleware implements Middleware
{
    public function handle(array $params): void
    {
        if (currentUserId() !== null) {
            redirect('/');
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Middleware;

/*
 * Only logged-in users get through.
 * Everyone else is sent to /login, and the page they wanted is remembered
 * so AuthController::login() can send them back to it afterwards.
 */
class AuthMiddleware implements Middleware
{
    public function handle(array $params): void
    {
        if (currentUserId() === null) {
            $_SESSION['intended'] = CURRENT_PATH;
            redirect('/login');
        }
    }
}

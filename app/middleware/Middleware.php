<?php

declare(strict_types=1);

namespace App\Middleware;

/*
 * Middleware runs BEFORE a route's handler.
 * Attach it to a route as the third argument:
 *
 *   $router->get('/favorites', $handler, [AuthMiddleware::class]);
 *
 * They run in the order listed. To let the request continue, just return.
 * To stop it, redirect() or send an error page and exit.
 */
interface Middleware
{
    /**
     * @param array $params the route placeholders, e.g. ['user' => '1'] for /profile/{user}/favorites
     */
    public function handle(array $params): void;
}

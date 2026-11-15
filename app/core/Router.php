<?php

declare(strict_types=1);

class Router
{
    private array $routes = [];
    /**
     * Register a Get route.
     */
     public function get(string $path, callable|array$handler): void
     {
        $this->add('GET', $path, $handler);
     }

     /**
     * Register a POST route.
     */
    public function post(string $path, callable|array $handler): void
    {
        $this->add('POST', $path, $handler);
    }

    /**
     * Store the route with its HTTP method and converted regex pattern.
     */
    private function add(string $method, string $path, callable|array $handler): void
    {
        // Convert {param} placeholders into named regex capture groups
        $pattern = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<$1>[^/]+)', $path);
        $pattern = '#^' . $pattern . '$#';

        $this->routes[] = [
            'method'  => $method,
            'pattern' => $pattern,
            'handler' => $handler,
        ];
    }

    /**
     * Match the current request URI and method against registered routes.
     */
    public function dispatch(string $uri, string $method): mixed
    {
        // Strip query strings (e.g., ?page=2) and trailing slashes
        $path = parse_url($uri, PHP_URL_PATH) ?? '/';
        $path = $path !== '/' ? rtrim($path, '/') : '/';

        foreach ($this->routes as $route) {
            if ($route['method'] === strtoupper($method) && preg_match($route['pattern'], $path, $matches)) {
                // Filter out numeric regex keys, keeping only named route parameters
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

                return $this->executeHandler($route['handler'], $params);
            }
        }

        // Handle 404 Not Found
        http_response_code(404);
        echo '404 Not Found';
        return null;
    }

    /**
     * Call the closure or instantiate the controller class and call its method.
     */
    private function executeHandler(callable|array $handler, array $params): mixed
    {
        if (is_array($handler)) {
            [$controllerClass, $method] = $handler;
            $controller = new $controllerClass();
            return call_user_func_array([$controller, $method], $params);
        }

        return call_user_func_array($handler, $params);
    }
}
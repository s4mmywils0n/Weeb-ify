<?php

declare(strict_types=1);

namespace App\Api;

use App\Api\Middleware\ApiMiddleware;

/*
 * Runs the request through each middleware in order, then the final handler:
 *
 *   RequestId -> Logger -> Cors -> ErrorHandler -> Negotiation -> Route -> JsonBody -> controller
 *
 * Each middleware gets the request and a $next callable. It can change the request,
 * stop early by returning/throwing, or change the response that $next gives back.
 */
class Pipeline
{
    /**
     * @param ApiMiddleware[] $middleware
     * @param callable(ApiRequest): ApiResponse $handler
     */
    public function __construct(private array $middleware, private $handler)
    {
    }

    public function handle(ApiRequest $request): ApiResponse
    {
        return $this->run(0, $request);
    }

    private function run(int $index, ApiRequest $request): ApiResponse
    {
        if (!isset($this->middleware[$index])) {
            return ($this->handler)($request);
        }

        return $this->middleware[$index]->process(
            $request,
            fn(ApiRequest $request): ApiResponse => $this->run($index + 1, $request)
        );
    }
}

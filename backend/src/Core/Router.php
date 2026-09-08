<?php

declare(strict_types=1);

namespace MediTrack\Core;

use MediTrack\Middleware\AuthMiddleware;
use MediTrack\Middleware\CsrfMiddleware;
use MediTrack\Middleware\RoleMiddleware;

/**
 * Minimal route table + dispatcher. Every route declares its own allowed
 * roles so permission checks can never be bypassed by hitting a URL the
 * frontend doesn't show a link to — see docs/02-architecture.md §4.
 */
final class Router
{
    /** @var list<array{method:string,pattern:string,regex:string,roles:list<string>,handler:callable}> */
    private array $routes = [];

    /** @param list<string> $roles pass ['public'] for unauthenticated routes */
    public function add(string $method, string $pattern, array $roles, callable $handler): void
    {
        $this->routes[] = [
            'method' => strtoupper($method),
            'pattern' => $pattern,
            'regex' => $this->toRegex($pattern),
            'roles' => $roles,
            'handler' => $handler,
        ];
    }

    private function toRegex(string $pattern): string
    {
        $escaped = preg_replace('#\{(\w+)\}#', '(?P<$1>[^/]+)', $pattern);
        return '#^' . $escaped . '$#';
    }

    public function dispatch(Request $request): void
    {
        $matchedPath = false;

        foreach ($this->routes as $route) {
            if (!preg_match($route['regex'], $request->path, $matches)) {
                continue;
            }
            $matchedPath = true;

            if ($route['method'] !== $request->method) {
                continue;
            }

            $params = array_filter($matches, static fn($key) => !is_int($key), ARRAY_FILTER_USE_KEY);
            $request->params = $params;

            $isPublic = in_array('public', $route['roles'], true);
            if (!$isPublic) {
                $request->user = AuthMiddleware::handle();
                RoleMiddleware::check($request->user, $route['roles']);
            }
            if (!in_array($request->method, ['GET', 'HEAD', 'OPTIONS'], true)) {
                CsrfMiddleware::verify($request, $isPublic);
            }

            ($route['handler'])($request);
            return;
        }

        if ($matchedPath) {
            Response::error('Method not allowed', 405);
        }
        Response::error('Resource not found', 404);
    }
}

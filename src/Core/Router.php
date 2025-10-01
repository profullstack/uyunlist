<?php

declare(strict_types=1);

namespace App\Core;

use Exception;

class Router
{
    private array $routes = [];
    private array $middleware = [];

    public function get(string $path, callable|string $handler, array $middleware = []): void
    {
        $this->addRoute('GET', $path, $handler, $middleware);
    }

    public function post(string $path, callable|string $handler, array $middleware = []): void
    {
        $this->addRoute('POST', $path, $handler, $middleware);
    }

    public function put(string $path, callable|string $handler, array $middleware = []): void
    {
        $this->addRoute('PUT', $path, $handler, $middleware);
    }

    public function delete(string $path, callable|string $handler, array $middleware = []): void
    {
        $this->addRoute('DELETE', $path, $handler, $middleware);
    }

    private function addRoute(string $method, string $path, callable|string $handler, array $middleware): void
    {
        $this->routes[] = [
            'method' => $method,
            'path' => $path,
            'handler' => $handler,
            'middleware' => $middleware,
            'pattern' => $this->pathToPattern($path)
        ];
    }

    private function pathToPattern(string $path): string
    {
        // Convert route parameters like {id} to regex patterns
        $pattern = preg_replace('/\{([^}]+)\}/', '(?P<$1>[^/]+)', $path);
        return '#^' . $pattern . '$#';
    }

    public function addMiddleware(string $name, callable $middleware): void
    {
        $this->middleware[$name] = $middleware;
    }

    public function dispatch(string $method, string $uri): array
    {
        // Remove query string from URI
        $uri = parse_url($uri, PHP_URL_PATH) ?? '/';
        
        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            if (preg_match($route['pattern'], $uri, $matches)) {
                // Extract named parameters
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                
                return [
                    'handler' => $route['handler'],
                    'params' => $params,
                    'middleware' => $route['middleware']
                ];
            }
        }

        throw new Exception('Route not found', 404);
    }

    public function executeMiddleware(array $middlewareNames, array $params = []): bool
    {
        foreach ($middlewareNames as $name) {
            if (!isset($this->middleware[$name])) {
                throw new Exception("Middleware '{$name}' not found");
            }

            $result = call_user_func($this->middleware[$name], $params);
            if ($result === false) {
                return false;
            }
        }

        return true;
    }

    public function url(string $name, array $params = []): string
    {
        // Simple URL generation - can be enhanced later
        $url = $name;
        foreach ($params as $key => $value) {
            $url = str_replace('{' . $key . '}', (string)$value, $url);
        }
        return $url;
    }

    public function redirect(string $url, int $code = 302): void
    {
        http_response_code($code);
        header('Location: ' . $url);
        exit;
    }

    public function redirectBack(string $default = '/'): void
    {
        $referer = $_SERVER['HTTP_REFERER'] ?? $default;
        $this->redirect($referer);
    }
}
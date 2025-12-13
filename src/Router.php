<?php

namespace Src;

use Src\Helpers\Response;

class Router
{
    private array $routes = [];
    private array $groupMiddlewares = [];

    public function get(string $path, string $handler): void
    {
        $this->addRoute('GET', $path, $handler);
    }

    public function post(string $path, string $handler): void
    {
        $this->addRoute('POST', $path, $handler);
    }

    public function put(string $path, string $handler): void
    {
        $this->addRoute('PUT', $path, $handler);
    }

    public function delete(string $path, string $handler): void
    {
        $this->addRoute('DELETE', $path, $handler);
    }

    public function group(array $options, callable $callback): void
    {
        $previousMiddlewares = $this->groupMiddlewares;

        if (isset($options['middleware'])) {
            $this->groupMiddlewares[] = $options['middleware'];
        }

        $callback($this);

        $this->groupMiddlewares = $previousMiddlewares;
    }

    private function addRoute(string $method, string $path, string $handler): void
    {
        $this->routes[] = [
            'method' => $method,
            'path' => $path,
            'handler' => $handler,
            'middlewares' => $this->groupMiddlewares,
        ];
    }

    public function dispatch(): void
    {
        $method = $_SERVER['REQUEST_METHOD'];
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            $pattern = $this->convertToRegex($route['path']);

            if (preg_match($pattern, $path, $matches)) {
                array_shift($matches);

                // Run middlewares
                foreach ($route['middlewares'] as $middleware) {
                    $middlewareClass = "Src\\Middleware\\" . ucfirst($middleware) . "Middleware";
                    if (!$middlewareClass::handle()) {
                        return;
                    }
                }

                // Call controller
                [$controller, $method] = explode('@', $route['handler']);
                $controllerClass = "Src\\Controllers\\{$controller}";

                if (!class_exists($controllerClass)) {
                    Response::error("Controller not found: {$controller}", 404);
                    return;
                }

                $instance = new $controllerClass();

                if (!method_exists($instance, $method)) {
                    Response::error("Method not found: {$method}", 404);
                    return;
                }

                // Convert numeric params to int
                $params = array_map(function ($param) {
                    return is_numeric($param) ? (int) $param : $param;
                }, $matches);

                call_user_func_array([$instance, $method], $params);
                return;
            }
        }

        Response::error('Route not found', 404);
    }

    private function convertToRegex(string $path): string
    {
        $pattern = preg_replace('/:\w+/', '([^/]+)', $path);
        return '#^' . $pattern . '$#';
    }
}

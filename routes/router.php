<?php
// Simple Router

class Router {
     private $routes = [];

    public function add($method, $path, $handler) {
        $this->routes[] = compact('method', 'path', 'handler');
    }

    public function dispatch($method, $uri) {
        // Remove query string 
        $uri = strtok($uri, '?');
        // Remove trailing slash (except for root)
        if ($uri !== '/' && substr($uri, -1) === '/') {
            $uri = rtrim($uri, '/');
        }
        foreach ($this->routes as $route) {
            $routePath = $route['path'];
            if ($routePath !== '/' && substr($routePath, -1) === '/') {
                $routePath = rtrim($routePath, '/');
            }
            if ($route['method'] === $method && $routePath === $uri) {
                try {
                    return call_user_func($route['handler']);
                } catch (\Throwable $e) {
                    http_response_code(500); 
                    echo json_encode([
                        'error' => 'Internal Server Error',
                        'message' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                    return;
                }
            }
        }
        http_response_code(404);
        echo json_encode(['error' => 'Not Found']);
    }
}

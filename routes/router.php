<?php
// Simple Router with Group Support

class Router {
    private $routes = [];

    /**
     * Register a route.
     */
    public function add($method, $path, $handler) {
        $this->routes[] = compact('method', 'path', 'handler');
    }

    /**
     * Group routes under a common prefix.
     *
     * Example:
     * $router->group('/api', function($r, $prefix) {
     *     $r->add('GET', $prefix . '/users', fn() => echo "Users");
     * });
     */
    public function group(string $prefix, callable $callback) {
        // Ensure prefix always starts with "/"
        if ($prefix !== '/' && substr($prefix, 0, 1) !== '/') {
            $prefix = '/' . $prefix;
        }
        // Remove trailing slash except root
        if ($prefix !== '/' && substr($prefix, -1) === '/') {
            $prefix = rtrim($prefix, '/');
        }

        // Pass router instance and prefix to callback
        $callback($this, $prefix);
    }

    /**
     * Dispatch the request to the matched route.
     */
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
                        'error'   => 'Internal Server Error',
                        'message' => $e->getMessage(),
                        'trace'   => $e->getTraceAsString()
                    ]);
                    return;
                }
            }
        }

        http_response_code(404);
        echo json_encode(['error' => 'Not Found']);
    }
}
 
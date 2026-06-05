<?php

namespace App\Core;

use App\Helpers\Response;

class Router {
    private $routes = [];

    public function add($method, $uri, $controllerAction, $middlewares = []) {
        $this->routes[] = [
            'method' => strtoupper($method),
            'uri' => $uri,
            'action' => $controllerAction,
            'middlewares' => $middlewares
        ];
    }

    public function get($uri, $action, $middlewares = []) {
        $this->add('GET', $uri, $action, $middlewares);
    }

    public function post($uri, $action, $middlewares = []) {
        $this->add('POST', $uri, $action, $middlewares);
    }

    public function dispatch($requestUri, $requestMethod) {
        $parsedUrl = parse_url($requestUri);
        $path = $parsedUrl['path'];

        // Remove trailing slash if not root
        if ($path !== '/' && substr($path, -1) === '/') {
            $path = rtrim($path, '/');
        }

        foreach ($this->routes as $route) {
            if ($route['uri'] === $path && $route['method'] === $requestMethod) {
                // Execute Middlewares
                foreach ($route['middlewares'] as $middleware) {
                    $middlewareInstance = new $middleware();
                    $middlewareInstance->handle();
                }

                // Execute Controller
                list($controller, $method) = explode('@', $route['action']);
                $controllerClass = "App\\Controllers\\$controller";
                
                if (class_exists($controllerClass) && method_exists($controllerClass, $method)) {
                    $instance = new $controllerClass();
                    $instance->$method();
                    return;
                }
            }
        }

        Response::json(['error' => 'Not Found'], 404);
    }
}

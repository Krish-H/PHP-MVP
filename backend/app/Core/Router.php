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

    public function put($uri, $action, $middlewares = []) {
        $this->add('PUT', $uri, $action, $middlewares);
    }

    public function patch($uri, $action, $middlewares = []) {
        $this->add('PATCH', $uri, $action, $middlewares);
    }

    public function delete($uri, $action, $middlewares = []) {
        $this->add('DELETE', $uri, $action, $middlewares);
    }

    public function dispatch($requestUri, $requestMethod) {
        $parsedUrl = parse_url($requestUri);
        $path = $parsedUrl['path'];

        // Remove trailing slash if not root
        if ($path !== '/' && substr($path, -1) === '/') {
            $path = rtrim($path, '/');
        }

        foreach ($this->routes as $route) {
            if ($route['method'] !== $requestMethod) {
                continue;
            }

            $pattern = preg_replace_callback('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', function ($matches) {
                return '(?P<' . $matches[1] . '>[^/]+)';
            }, $route['uri']);

            $pattern = '#^' . $pattern . '$#';
            $matches = [];

            if (!preg_match($pattern, $path, $matches)) {
                continue;
            }

            // Extract route parameters
            $params = [];
            foreach ($matches as $key => $value) {
                if (!is_int($key)) {
                    $params[$key] = $value;
                }
            }

            // Execute Middlewares
            foreach ($route['middlewares'] as $middleware) {
                if (is_array($middleware) && isset($middleware[0])) {
                    $middlewareClass = $middleware[0];
                    $middlewareArgs = $middleware[1] ?? [];
                    $middlewareInstance = new $middlewareClass();
                    $middlewareInstance->handle($middlewareArgs);
                } else {
                    $middlewareInstance = new $middleware();
                    $middlewareInstance->handle();
                }
            }

            list($controller, $method) = explode('@', $route['action']);
            $controllerClass = "App\\Controllers\\$controller";

            if (class_exists($controllerClass) && method_exists($controllerClass, $method)) {
                $instance = new $controllerClass();
                if (!empty($params)) {
                    call_user_func_array([$instance, $method], [$params]);
                } else {
                    $instance->$method();
                }
                return;
            }
        }

        Response::json(['error' => 'Not Found'], 404);
    }
}

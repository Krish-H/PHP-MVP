<?php

// api.php

use App\Middleware\CsrfMiddleware;
use App\Middleware\AuthMiddleware;

/** @var \App\Core\Router $router */

// Public routes
$router->get('/api/csrf-token', 'AuthController@getCsrfToken');

// Registration and Login (Requires CSRF)
$router->post('/api/register', 'AuthController@register', [CsrfMiddleware::class]);
$router->post('/api/login', 'AuthController@login', [CsrfMiddleware::class]);

// Protected routes (Requires Auth AND CSRF)
// Example for getting current user profile
$router->get('/api/profile', 'AuthController@profile', [AuthMiddleware::class]);


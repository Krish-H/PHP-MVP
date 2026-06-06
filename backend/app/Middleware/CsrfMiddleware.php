<?php

namespace App\Middleware;

use App\Helpers\Request;
use App\Helpers\Response;
use App\Security\Csrf;

class CsrfMiddleware {
    public function handle() {
        // Skip for GET requests
        if ($_SERVER['REQUEST_METHOD'] === 'GET' || $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            return;
        }

        $headers = getallheaders();
        $token = '';

        if (isset($headers['X-CSRF-TOKEN'])) {
            $token = $headers['X-CSRF-TOKEN'];
        } else {
            $body = Request::body();
            if (isset($body['csrf_token'])) {
                $token = $body['csrf_token'];
            }
        }

        if (!Csrf::validateToken($token)) {
            Response::json(['error' => 'Invalid CSRF token'], 403);
        }
    }
}

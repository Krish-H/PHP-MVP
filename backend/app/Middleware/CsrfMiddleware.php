<?php

namespace App\Middleware;

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

        // Check header first
        if (isset($headers['X-CSRF-TOKEN'])) {
            $token = $headers['X-CSRF-TOKEN'];
        } else {
            // Check body if present
            $body = json_decode(file_get_contents('php://input'), true);
            if (isset($body['csrf_token'])) {
                $token = $body['csrf_token'];
            }
        }

        if (!Csrf::validateToken($token)) {
            Response::json(['error' => 'Invalid CSRF token'], 403);
        }
    }
}

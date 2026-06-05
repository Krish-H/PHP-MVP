<?php

namespace App\Middleware;

use App\Helpers\Response;
use App\Security\JwtAuth;

class AuthMiddleware {
    public function handle() {
        // Check if token exists in session
        if (!isset($_SESSION['access_token'])) {
            // Fallback: check Authorization header for stateless testing if needed
            $headers = getallheaders();
            if (!isset($headers['Authorization'])) {
                Response::json(['error' => 'Unauthorized - No token provided'], 401);
            }
            $authHeader = $headers['Authorization'];
            $token = str_replace('Bearer ', '', $authHeader);
        } else {
            $token = $_SESSION['access_token'];
        }

        $decoded = JwtAuth::verifyToken($token);

        if (!$decoded) {
            Response::json(['error' => 'Unauthorized - Invalid or expired token'], 401);
        }

        // Store user details in request context or session for later use
        $_SESSION['current_user_id'] = $decoded['user_id'];
        if(isset($decoded['tenant_id'])) {
            $_SESSION['current_tenant_id'] = $decoded['tenant_id'];
        }
    }
}

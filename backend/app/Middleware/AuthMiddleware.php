<?php

namespace App\Middleware;

use App\Helpers\Response;
use App\Security\JWT;

class AuthMiddleware {
    public function handle() {
        $authorizationHeader = $this->getAuthorizationHeader();

        if (!$authorizationHeader) {
            Response::json(['error' => 'Unauthorized'], 401);
        }

        if (!preg_match('/^Bearer\s+(.+)$/i', trim($authorizationHeader), $matches)) {
            Response::json(['error' => 'Unauthorized'], 401);
        }

        $token = $matches[1];
        $decoded = JWT::verifyToken($token);

        if (!$decoded) {
            Response::json(['error' => 'Unauthorized'], 401);
        }

        $_SESSION['current_user_id'] = $decoded['user_id'] ?? null;
        $_SESSION['current_tenant_id'] = $decoded['tenant_id'] ?? null;
        $_SESSION['current_role_id'] = $decoded['role_id'] ?? null;
    }

    private function getAuthorizationHeader() {
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
            foreach ($headers as $name => $value) {
                if (strtolower($name) === 'authorization') {
                    return $value;
                }
            }
        }

        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            return $_SERVER['HTTP_AUTHORIZATION'];
        }

        if (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            return $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        }

        return null;
    }
}

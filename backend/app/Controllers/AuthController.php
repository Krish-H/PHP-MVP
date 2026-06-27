<?php

namespace App\Controllers;

use App\Config\Database;
use App\Config\Env;
use App\Helpers\Request;
use App\Helpers\Response;
use App\Helpers\Validator;
use App\Services\AuthService;
use App\Security\Csrf;
use Exception;

class AuthController {
    private $authService;

    public function __construct() {
        $this->authService = new AuthService();
    }



    public function register() {
        $data = Request::body();

        if (!is_array($data)) {
            Response::json(['error' => 'Invalid request body'], 400);
        }

        if (!Validator::required($data, ['email', 'password'])) {
            Response::json(['error' => 'Email and password are required'], 400);
        }

        if (!Validator::email($data['email'])) {
            Response::json(['error' => 'Invalid email format'], 400);
        }

        if (!Validator::minLength($data['password'], 8)) {
            Response::json(['error' => 'Password must be at least 8 characters long'], 400);
        }

        try {
            $result = $this->authService->registerUser($data);

            Response::json([
                'message' => 'Registration successful',
                'user_id' => $result['user_id']
            ], 201);
        }catch (Exception $e) {
    // Ensure the status code is actually a valid HTTP integer, otherwise default to 500
    $code = $e->getCode();
    $statusCode = (is_numeric($code) && $code >= 100 && $code < 600) ? (int)$code : 500;
    
    Response::json(['error' => $e->getMessage()], $statusCode);
}
    }

    public function login() {
        $data = Request::body();

        if (!is_array($data)) {
            Response::json(['error' => 'Invalid request body'], 400);
        }

        if (!Validator::required($data, ['email', 'password'])) {
            Response::json(['error' => 'Email and password are required'], 400);
        }

        try {
            $result = $this->authService->loginUser($data);
            $this->setRefreshTokenCookie($result['tokens']['refresh_token']);

            $csrfToken = Csrf::regenerateToken();

            Response::json([
                'message' => 'Login successful',
                'user' => $result['user'],
                'access_token' => $result['tokens']['access_token'],
                'refresh_token' => $result['tokens']['refresh_token'],
                'csrf_token' => $csrfToken
            ], 200);
        } catch (Exception $e) {
            $code = $e->getCode();
            $statusCode = (is_numeric($code) && $code >= 100 && $code < 600) ? (int)$code : 500;
            Response::json(['error' => $e->getMessage()], $statusCode);
        }
    }

    public function refresh() {
        $refreshToken = $_COOKIE['refresh_token'] ?? null;

        if (!$refreshToken) {
            Response::json(['error' => 'Refresh token is required'], 401);
        }

        try {
            $tokens = $this->authService->refreshAccessToken($refreshToken);
            $this->setRefreshTokenCookie($tokens['refresh_token']);
           

            Response::json([
                'message' => 'Token refreshed',
                'access_token' => $tokens['access_token'],
                
            ], 200);
        }catch (Exception $e) {
    // Ensure the status code is actually a valid HTTP integer, otherwise default to 500
    $code = $e->getCode();
    $statusCode = (is_numeric($code) && $code >= 100 && $code < 600) ? (int)$code : 500;
    
    Response::json(['error' => $e->getMessage()], $statusCode);
}
    }

    public function csrfToken() {
        try {
            $csrfToken = Csrf::generateToken();
            Response::json([
                'message' => 'CSRF token retrieved',
                'csrf_token' => $csrfToken
            ], 200);
        } catch (Exception $e) {
            $code = $e->getCode();
            $statusCode = (is_numeric($code) && $code >= 100 && $code < 600) ? (int)$code : 500;
            Response::json(['error' => $e->getMessage()], $statusCode);
        }
    }

    public function logout() {
        try {
            $userId = $_SESSION['current_user_id'] ?? null;
            if ($userId) {
                $this->authService->logout($userId);
            }
            unset($_SESSION['access_token'], $_SESSION['current_user_id'], $_SESSION['current_tenant_id'], $_SESSION['current_role_id'], $_SESSION['csrf_token']);
            $this->clearRefreshTokenCookie();
            Response::json(['message' => 'Logged out successfully'], 200);
        }catch (Exception $e) {
    // Ensure the status code is actually a valid HTTP integer, otherwise default to 500
    $code = $e->getCode();
    $statusCode = (is_numeric($code) && $code >= 100 && $code < 600) ? (int)$code : 500;
    
    Response::json(['error' => $e->getMessage()], $statusCode);
}
    }

    private function setRefreshTokenCookie($refreshToken) {
        setcookie('refresh_token', $refreshToken, [
            'expires' => time() + (int) Env::get('JWT_REFRESH_TOKEN_EXPIRATION', 2592000),
            'path' => '/',
            'secure' => false,
            'httponly' => true,
            'samesite' => 'Strict'
        ]);
    }

    private function clearRefreshTokenCookie() {
        setcookie('refresh_token', '', [
            'expires' => time() - 3600,
            'path' => '/',
            'secure' => false,
            'httponly' => true,
            'samesite' => 'Strict'
        ]);
    }

    public function profile() {
        try {
            $userId = $_SESSION['current_user_id'];
            $user = $this->authService->getUserProfile($userId);
            Response::json(['message' => 'Profile retrieved', 'user' => $user], 200);
        }catch (Exception $e) {
    // Ensure the status code is actually a valid HTTP integer, otherwise default to 500
    $code = $e->getCode();
    $statusCode = (is_numeric($code) && $code >= 100 && $code < 600) ? (int)$code : 500;
    
    Response::json(['error' => $e->getMessage()], $statusCode);
}
    }

    public function changePassword() {
        $userId = $_SESSION['current_user_id'] ?? null;
        if (!$userId) {
            Response::json(['error' => 'Unauthorized'], 401);
        }

        $data = Request::body();

        if (!is_array($data)) {
            Response::json(['error' => 'Invalid request body'], 400);
        }

        if (!Validator::required($data, ['current_password', 'new_password', 'confirm_password'])) {
            Response::json(['error' => 'Current password, new password, and confirm password are required'], 400);
        }

        if (!Validator::minLength($data['new_password'], 8)) {
            Response::json(['error' => 'Password must be at least 8 characters long'], 400);
        }

        try {
            $this->authService->changePassword(
                $userId,
                $data['current_password'],
                $data['new_password'],
                $data['confirm_password']
            );

            Response::json([
                'success' => true,
                'message' => 'Password changed successfully'
            ], 200);
        } catch (Exception $e) {
            $code = $e->getCode();
            $statusCode = (is_numeric($code) && $code >= 100 && $code < 600) ? (int)$code : 400;
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], $statusCode);
        }
    }
}

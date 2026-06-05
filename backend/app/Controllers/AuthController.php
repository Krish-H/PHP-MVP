<?php

namespace App\Controllers;

use App\Helpers\Response;
use App\Helpers\Validator;
use App\Services\AuthService;
use App\Security\Crypto;
use App\Security\Csrf;
use Exception;

class AuthController {
    
    private $authService;

    public function __construct() {
        $this->authService = new AuthService();
    }

    // Endpoint to get a CSRF token for the very first request
    public function getCsrfToken() {
        Response::json(['csrf_token' => Csrf::generateToken()]);
    }

    public function register() {
        $body = json_decode(file_get_contents('php://input'), true);

        if (!isset($body['payload'])) {
            Response::json(['error' => 'Missing encrypted payload'], 400);
        }

        // Decrypt the payload
        $data = Crypto::decrypt($body['payload']);
        if (!is_array($data)) {
            Response::json(['error' => 'Invalid encrypted data'], 400);
        }

        // Validate required fields
        if (!Validator::required($data, ['email', 'password'])) {
            Response::json(['error' => 'Email and password are required'], 400);
        }

        // Validate email format
        if (!Validator::email($data['email'])) {
            Response::json(['error' => 'Invalid email format'], 400);
        }

        // Validate password length
        if (!Validator::minLength($data['password'], 8)) {
            Response::json(['error' => 'Password must be at least 8 characters long'], 400);
        }

        try {
            // Delegate business logic to Service
            $result = $this->authService->registerUser($data);

            // Store Access Token in Session
            $_SESSION['access_token'] = $result['access_token'];

            Response::json([
                'message' => 'Registration successful',
                'user_id' => $result['user_id']
            ], 201, true);

        } catch (Exception $e) {
            $statusCode = $e->getCode() ?: 500;
            Response::json(['error' => $e->getMessage()], $statusCode);
        }
    }

    public function login() {
        $body = json_decode(file_get_contents('php://input'), true);

        if (!isset($body['payload'])) {
            Response::json(['error' => 'Missing encrypted payload'], 400);
        }

        $data = Crypto::decrypt($body['payload']);

        if (!Validator::required($data, ['email', 'password'])) {
            Response::json(['error' => 'Email and password are required'], 400);
        }

        try {
            // Delegate business logic to Service
            $result = $this->authService->loginUser($data);

            // Store Access Token in Session
            $_SESSION['access_token'] = $result['tokens']['access_token'];

            Response::json([
                'message' => 'Login successful',
                'user' => $result['user']
            ], 200, true);

        } catch (Exception $e) {
            $statusCode = $e->getCode() ?: 500;
            Response::json(['error' => $e->getMessage()], $statusCode);
        }
    }

    public function profile() {
        $userId = $_SESSION['current_user_id'];
        
        try {
            // Delegate business logic to Service
            $user = $this->authService->getUserProfile($userId);

            Response::json([
                'message' => 'Profile retrieved',
                'user' => $user
            ], 200, true);

        } catch (Exception $e) {
            $statusCode = $e->getCode() ?: 500;
            Response::json(['error' => $e->getMessage()], $statusCode);
        }
    }
}

<?php

namespace App\Services;

use App\Repositories\UserRepository;
use App\Repositories\TokenRepository;
use App\Security\JWT;
use Exception;

class AuthService {
    private $userRepo;
    private $tokenRepo;

    public function __construct() {
        $this->userRepo = new UserRepository();
        $this->tokenRepo = new TokenRepository();
    }

    public function registerUser($data) {
        $email = $data['email'] ?? null;
        $password = $data['password'] ?? null;
        $roleId = $data['role_id'] ?? 1;
        $tenantId = $data['tenant_id'] ?? 1;

        if ($this->userRepo->findByEmail($email)) {
            throw new Exception('User already exists', 409);
        }

        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $userId = $this->userRepo->create($email, $hashedPassword, $roleId, $tenantId);

        return [
            'user_id' => $userId
        ];
    }

    public function loginUser($data) {
        $email = $data['email'] ?? null;
        $password = $data['password'] ?? null;

        $user = $this->userRepo->findByEmail($email);

        if (!$user || !password_verify($password, $user['password_hash'])) {
            throw new Exception('Invalid credentials', 401);
        }

        $this->tokenRepo->revokeAllForUser($user['id']);

        $tokens = $this->generateUserTokens($user['id'], $user['tenant_id'], $user['role_id']);
        
        return [
            'tokens' => $tokens,
            'user' => [
                'id' => $user['id'],
                'email' => $user['email'],
                'role_id' => $user['role_id'],
                'tenant_id' => $user['tenant_id']
            ]
        ];
    }

    public function refreshAccessToken($refreshToken) {
        $tokenRow = $this->tokenRepo->findValidByToken($refreshToken);
        if (!$tokenRow) {
            throw new Exception('Refresh token invalid or expired', 401);
        }

        $decoded = JWT::verifyToken($refreshToken);
        if (!$decoded) {
            $this->tokenRepo->revokeById($tokenRow['id']);
            throw new Exception('Refresh token invalid or expired', 401);
        }

        $user = $this->userRepo->findById($decoded['user_id']);
        if (!$user) {
            throw new Exception('User not found', 404);
        }

        $this->tokenRepo->revokeById($tokenRow['id']);
        $tokens = $this->generateUserTokens($user['id'], $user['tenant_id'], $user['role_id']);

        return $tokens;
    }

    public function logout($userId) {
        $this->tokenRepo->revokeAllForUser($userId);
        unset($_SESSION['access_token'], $_SESSION['current_user_id'], $_SESSION['current_role_id'], $_SESSION['current_tenant_id']);

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }

        return true;
    }

    private function generateUserTokens($userId, $tenantId, $roleId) {
        $accessToken = JWT::generateAccessToken($userId, $tenantId, $roleId);
        $refreshToken = JWT::generateRefreshToken($userId);

        $this->tokenRepo->store($userId, $refreshToken);

        return [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'user_id' => $userId
        ];
    }
    
    public function getUserProfile($userId) {
        $user = $this->userRepo->findById($userId);
        if (!$user) {
            throw new Exception('User not found', 404);
        }
        unset($user['password_hash']);
        return $user;
    }
}

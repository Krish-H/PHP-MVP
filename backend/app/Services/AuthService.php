<?php

namespace App\Services;

use App\Repositories\UserRepository;
use App\Repositories\TokenRepository;
use App\Security\JwtAuth;
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

        return $this->generateUserTokens($userId, $tenantId);
    }

    public function loginUser($data) {
        $email = $data['email'] ?? null;
        $password = $data['password'] ?? null;

        $user = $this->userRepo->findByEmail($email);

        if (!$user || !password_verify($password, $user['password_hash'])) {
            throw new Exception('Invalid credentials', 401);
        }

        $this->tokenRepo->revokeAllForUser($user['id']);

        $tokens = $this->generateUserTokens($user['id'], $user['tenant_id']);
        
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

    private function generateUserTokens($userId, $tenantId) {
        $accessToken = JwtAuth::generateAccessToken($userId, $tenantId);
        $refreshToken = JwtAuth::generateRefreshToken($userId);

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

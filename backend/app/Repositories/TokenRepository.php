<?php

namespace App\Repositories;

use App\Config\Database;

class TokenRepository {
    private $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    public function store($userId, $refreshToken) {
        $tokenHash = $this->hashToken($refreshToken);

        $stmt = $this->db->prepare('
            INSERT INTO refresh_tokens (user_id, token_hash, expires_at) 
            VALUES (:user_id, :token_hash, DATE_ADD(NOW(), INTERVAL 30 DAY))
        ');
        $stmt->execute([
            'user_id' => $userId,
            'token_hash' => $tokenHash
        ]);
    }

    public function findValidByToken($refreshToken) {
        $tokenHash = $this->hashToken($refreshToken);

        $stmt = $this->db->prepare('
            SELECT id, user_id, token_hash, expires_at, revoked FROM refresh_tokens 
            WHERE token_hash = :token_hash 
              AND revoked = 0 
              AND expires_at > NOW() 
            LIMIT 1
        ');
        $stmt->execute(['token_hash' => $tokenHash]);
        return $stmt->fetch();
    }

    public function revokeAllForUser($userId) {
        $stmt = $this->db->prepare('UPDATE refresh_tokens SET revoked = 1 WHERE user_id = :user_id');
        $stmt->execute(['user_id' => $userId]);
    }

    public function revokeById($id) {
        $stmt = $this->db->prepare('UPDATE refresh_tokens SET revoked = 1 WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public function deleteUserTokens($userId) {
        $stmt = $this->db->prepare('DELETE FROM refresh_tokens WHERE user_id = :user_id');
        return $stmt->execute(['user_id' => $userId]);
    }

    private function hashToken($token) {
        return hash('sha256', $token);
    }
}

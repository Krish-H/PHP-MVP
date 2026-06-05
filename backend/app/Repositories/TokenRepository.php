<?php

namespace App\Repositories;

use App\Config\Database;

class TokenRepository {
    private $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    public function store($userId, $tokenHash) {
        $stmt = $this->db->prepare('
            INSERT INTO refresh_tokens (user_id, token_hash, expires_at) 
            VALUES (:user_id, :token_hash, DATE_ADD(NOW(), INTERVAL 30 DAY))
        ');
        $stmt->execute([
            'user_id' => $userId,
            'token_hash' => $tokenHash
        ]);
    }

    public function revokeAllForUser($userId) {
        $stmt = $this->db->prepare('UPDATE refresh_tokens SET revoked = 1 WHERE user_id = :user_id');
        $stmt->execute(['user_id' => $userId]);
    }
}

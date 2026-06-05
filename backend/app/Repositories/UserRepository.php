<?php

namespace App\Repositories;

use App\Config\Database;

class UserRepository {
    private $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    public function findByEmail($email) {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        return $stmt->fetch();
    }

    public function findById($id) {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

    public function create($email, $passwordHash, $roleId, $tenantId) {
        $stmt = $this->db->prepare('
            INSERT INTO users (email, password_hash, role_id, tenant_id) 
            VALUES (:email, :password_hash, :role_id, :tenant_id)
        ');
        $stmt->execute([
            'email' => $email,
            'password_hash' => $passwordHash,
            'role_id' => $roleId,
            'tenant_id' => $tenantId
        ]);
        return $this->db->lastInsertId();
    }
}

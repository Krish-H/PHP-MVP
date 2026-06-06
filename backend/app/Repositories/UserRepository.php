<?php

namespace App\Repositories;

use App\Config\Database;
use App\Security\AES;

class UserRepository {
    private $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    public function findByEmail($email) {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE email = :email AND is_active = 1 LIMIT 1');
        $stmt->execute(['email' => $email]);
        return $stmt->fetch();
    }

    public function findById($id) {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

    public function create($email, $passwordHash, $roleId, $tenantId, $isActive = 1, $encryptedProfile = null) {
        if ($encryptedProfile === null) {
            $encryptedProfile = AES::encrypt(json_encode([]), $_ENV['AES_KEY']);
        }

        $stmt = $this->db->prepare('
            INSERT INTO users (email, password_hash, role_id, tenant_id, is_active, encrypted_profile) 
            VALUES (:email, :password_hash, :role_id, :tenant_id, :is_active, :encrypted_profile)
        ');
        $stmt->execute([
            'email' => $email,
            'password_hash' => $passwordHash,
            'role_id' => $roleId,
            'tenant_id' => $tenantId,
            'is_active' => $isActive,
            'encrypted_profile' => $encryptedProfile
        ]);
        return $this->db->lastInsertId();
    }

    public function listStaff($tenantId) {
        $stmt = $this->db->prepare('SELECT u.id, u.email, u.role_id, u.is_active, r.name AS role_name, u.created_at FROM users u JOIN roles r ON u.role_id = r.id WHERE u.tenant_id = :tenant_id ORDER BY u.created_at DESC');
        $stmt->execute(['tenant_id' => $tenantId]);
        return $stmt->fetchAll();
    }

    public function update($id, $tenantId, $data) {
        $fields = [];
        $params = ['id' => $id, 'tenant_id' => $tenantId];

        if (isset($data['email'])) {
            $fields[] = 'email = :email';
            $params['email'] = $data['email'];
        }
        if (isset($data['role_id'])) {
            $fields[] = 'role_id = :role_id';
            $params['role_id'] = $data['role_id'];
        }
        if (isset($data['password'])) {
            $fields[] = 'password_hash = :password_hash';
            $params['password_hash'] = password_hash($data['password'], PASSWORD_DEFAULT);
        }
        if (isset($data['is_active'])) {
            $fields[] = 'is_active = :is_active';
            $params['is_active'] = $data['is_active'];
        }

        if (empty($fields)) {
            return false;
        }

        $sql = 'UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = :id AND tenant_id = :tenant_id';
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    public function toggleActive($id, $tenantId, $status) {
        $stmt = $this->db->prepare('UPDATE users SET is_active = :status WHERE id = :id AND tenant_id = :tenant_id');
        return $stmt->execute(['status' => $status, 'id' => $id, 'tenant_id' => $tenantId]);
    }
}

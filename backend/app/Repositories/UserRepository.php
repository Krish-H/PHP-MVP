<?php

namespace App\Repositories;

use App\Config\Database;

class UserRepository {
    private $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    public function findByEmail($email) {
        $stmt = $this->db->prepare('SELECT id, role_id, email, password_hash, name, is_active FROM users WHERE email = :email AND is_active = 1 AND deleted_at IS NULL LIMIT 1');
        $stmt->execute(['email' => $email]);
        return $stmt->fetch();
    }

    public function findById($id) {
        $stmt = $this->db->prepare('SELECT id, role_id, email, name, is_active, created_at, updated_at FROM users WHERE id = :id AND deleted_at IS NULL LIMIT 1');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

    public function create($email, $passwordHash, $roleId, $isActive = 1, $name = null) {
        $stmt = $this->db->prepare('
            INSERT INTO users (email, password_hash, role_id, is_active, name) 
            VALUES (:email, :password_hash, :role_id, :is_active, :name)
        ');
        $stmt->execute([
            'email' => $email,
            'password_hash' => $passwordHash,
            'role_id' => $roleId,
            'is_active' => $isActive,
            'name' => $name
        ]);
        return $this->db->lastInsertId();
    }

    public function listStaff() {
        $stmt = $this->db->prepare('SELECT u.id, u.email, u.role_id, u.is_active, r.name AS role_name, u.created_at FROM users u JOIN roles r ON u.role_id = r.id WHERE u.deleted_at IS NULL ORDER BY u.created_at DESC');
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function update($id, $data) {
        $fields = [];
        $params = ['id' => $id];

        if (isset($data['name'])) {
            $fields[] = 'name = :name';
            $params['name'] = $data['name'];
        }
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

        $sql = 'UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = :id AND deleted_at IS NULL';
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    public function toggleActive($id, $status) {
        $stmt = $this->db->prepare('UPDATE users SET is_active = :status WHERE id = :id AND deleted_at IS NULL');
        return $stmt->execute(['status' => $status, 'id' => $id]);
    }

    public function findByIdWithTenant($id) {
        $stmt = $this->db->prepare('
            SELECT id, name, email, role_id, is_active, created_at, updated_at 
            FROM users 
            WHERE id = :id AND deleted_at IS NULL 
            LIMIT 1
        ');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

    public function emailExists($email, $excludeId = null) {
        $sql = 'SELECT COUNT(*) FROM users WHERE email = :email';
        $params = ['email' => $email];
        if ($excludeId !== null) {
            $sql .= ' AND id != :exclude_id';
            $params['exclude_id'] = $excludeId;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn() > 0;
    }

    public function softDelete($id) {
        $stmt = $this->db->prepare('
            UPDATE users 
            SET deleted_at = CURRENT_TIMESTAMP, is_active = 0 
            WHERE id = :id AND deleted_at IS NULL
        ');
        return $stmt->execute([
            'id' => $id
        ]);
    }

    public function getUsers($page = 1, $limit = 10, $name = null, $email = null, $excludeRole = null) {
        $where = ['deleted_at IS NULL'];
        $params = [];

        if ($name !== null && trim($name) !== '') {
            $where[] = 'name LIKE :name';
            $params['name'] = '%' . trim($name) . '%';
        }

        if ($email !== null && trim($email) !== '') {
            $where[] = 'email LIKE :email';
            $params['email'] = '%' . trim($email) . '%';
        }
        
        if ($excludeRole !== null) {
            $where[] = 'role_id != :exclude_role';
            $params['exclude_role'] = (int)$excludeRole;
        }

        $whereSql = implode(' AND ', $where);

        $countSql = "SELECT COUNT(*) FROM users WHERE $whereSql";
        $countStmt = $this->db->prepare($countSql);
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $offset = ($page - 1) * $limit;
        $sql = "SELECT id, name, email, role_id, is_active, created_at, updated_at 
                FROM users 
                WHERE $whereSql 
                ORDER BY created_at DESC 
                LIMIT :limit OFFSET :offset";

        $stmt = $this->db->prepare($sql);

        foreach ($params as $key => $val) {
            $stmt->bindValue(':' . $key, $val);
        }
        $stmt->bindValue(':limit', (int)$limit, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int)$offset, \PDO::PARAM_INT);

        $stmt->execute();
        $users = $stmt->fetchAll();

        return [
            'data' => $users,
            'pagination' => [
                'total' => $total,
                'page' => (int)$page,
                'limit' => (int)$limit,
                'total_pages' => (int)ceil($total / $limit)
            ]
        ];
    }

    public function updatePassword($userId, $hashedPassword) {
        $stmt = $this->db->prepare('UPDATE users SET password_hash = :password_hash, updated_at = CURRENT_TIMESTAMP WHERE id = :id AND deleted_at IS NULL');
        return $stmt->execute([
            'password_hash' => $hashedPassword,
            'id' => $userId
        ]);
    }

    public function getAvailablePatientUsers() {
        $stmt = $this->db->prepare('
            SELECT id, name, email 
            FROM users 
            WHERE role_id = :role_id 
              AND is_active = 1 
              AND deleted_at IS NULL 
              AND id NOT IN (
                  SELECT patient_user_id 
                  FROM patients 
                  WHERE patient_user_id IS NOT NULL 
                    AND is_deleted = 0
              )
            ORDER BY name ASC
        ');
        $stmt->execute(['role_id' => \App\Config\Roles::PATIENT]);
        return $stmt->fetchAll();
    }
}

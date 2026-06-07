<?php

namespace App\Repositories;

use App\Config\Database;

class UserRepository {
    private $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    public function findByEmail($email) {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE email = :email AND is_active = 1 AND deleted_at IS NULL LIMIT 1');
        $stmt->execute(['email' => $email]);
        return $stmt->fetch();
    }

    public function findById($id) {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE id = :id AND deleted_at IS NULL LIMIT 1');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

    public function create($email, $passwordHash, $roleId, $tenantId, $isActive = 1, $name = null) {
        $stmt = $this->db->prepare('
            INSERT INTO users (email, password_hash, role_id, tenant_id, is_active, name) 
            VALUES (:email, :password_hash, :role_id, :tenant_id, :is_active, :name)
        ');
        $stmt->execute([
            'email' => $email,
            'password_hash' => $passwordHash,
            'role_id' => $roleId,
            'tenant_id' => $tenantId,
            'is_active' => $isActive,
            'name' => $name
        ]);
        return $this->db->lastInsertId();
    }

    public function listStaff($tenantId) {
        $stmt = $this->db->prepare('SELECT u.id, u.email, u.role_id, u.is_active, r.name AS role_name, u.created_at FROM users u JOIN roles r ON u.role_id = r.id WHERE u.tenant_id = :tenant_id AND u.deleted_at IS NULL ORDER BY u.created_at DESC');
        $stmt->execute(['tenant_id' => $tenantId]);
        return $stmt->fetchAll();
    }

    public function update($id, $tenantId, $data) {
        $fields = [];
        $params = ['id' => $id, 'tenant_id' => $tenantId];

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

        $sql = 'UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = :id AND tenant_id = :tenant_id AND deleted_at IS NULL';
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    public function toggleActive($id, $tenantId, $status) {
        $stmt = $this->db->prepare('UPDATE users SET is_active = :status WHERE id = :id AND tenant_id = :tenant_id AND deleted_at IS NULL');
        return $stmt->execute(['status' => $status, 'id' => $id, 'tenant_id' => $tenantId]);
    }

    public function findByIdWithTenant($id, $tenantId) {
        $stmt = $this->db->prepare('
            SELECT id, tenant_id, name, email, role_id, created_at, updated_at 
            FROM users 
            WHERE id = :id AND tenant_id = :tenant_id AND deleted_at IS NULL 
            LIMIT 1
        ');
        $stmt->execute(['id' => $id, 'tenant_id' => $tenantId]);
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

    public function softDelete($id, $tenantId) {
        $stmt = $this->db->prepare('
            UPDATE users 
            SET deleted_at = CURRENT_TIMESTAMP, is_active = 0 
            WHERE id = :id AND tenant_id = :tenant_id AND deleted_at IS NULL
        ');
        return $stmt->execute([
            'id' => $id,
            'tenant_id' => $tenantId
        ]);
    }

    public function getUsers($tenantId, $page = 1, $limit = 10, $name = null, $email = null) {
        $where = ['tenant_id = :tenant_id', 'deleted_at IS NULL'];
        $params = ['tenant_id' => $tenantId];

        if ($name !== null && trim($name) !== '') {
            $where[] = 'name LIKE :name';
            $params['name'] = '%' . trim($name) . '%';
        }

        if ($email !== null && trim($email) !== '') {
            $where[] = 'email LIKE :email';
            $params['email'] = '%' . trim($email) . '%';
        }

        $whereSql = implode(' AND ', $where);

        $countSql = "SELECT COUNT(*) FROM users WHERE $whereSql";
        $countStmt = $this->db->prepare($countSql);
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $offset = ($page - 1) * $limit;
        $sql = "SELECT id, tenant_id, name, email, role_id, created_at, updated_at 
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
}

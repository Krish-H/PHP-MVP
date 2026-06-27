<?php

namespace App\Repositories;

use App\Config\Database;
use App\Config\Roles;

class StaffRepository {
    private $db;

    private const STAFF_ROLES = [
        Roles::PROVIDER,
        Roles::NURSE,
        Roles::PHARMACIST,
        Roles::RECEPTIONIST
    ];

    public function __construct() {
        $this->db = Database::getConnection();
    }

    public function createStaff($name, $email, $passwordHash, $roleId, $isActive = 1) {
        $stmt = $this->db->prepare('INSERT INTO users (name, email, password_hash, role_id, is_active) VALUES (:name, :email, :password_hash, :role_id, :is_active)');
        $stmt->execute([
            'name' => $name,
            'email' => $email,
            'password_hash' => $passwordHash,
            'role_id' => $roleId,
            'is_active' => $isActive
        ]);

        return $this->db->lastInsertId();
    }

    public function getStaffList($page = 1, $limit = 20, $roleId = null) {
        $page = max(1, (int)$page);
        $limit = max(1, (int)$limit);
        $offset = ($page - 1) * $limit;

        $where = ['u.deleted_at IS NULL'];
        $params = [];

        $rolesStr = implode(',', self::STAFF_ROLES);
        $where[] = "u.role_id IN ($rolesStr)";

        if ($roleId !== null) {
            $where[] = 'u.role_id = :role_id';
            $params['role_id'] = $roleId;
        }

        $whereSql = implode(' AND ', $where);

        $countSql = "SELECT COUNT(*) FROM users u WHERE $whereSql";
        $countStmt = $this->db->prepare($countSql);
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $sql = "SELECT u.id, u.name, u.email, u.role_id, r.name AS role_name, u.is_active, u.created_at, u.updated_at
                FROM users u
                JOIN roles r ON u.role_id = r.id
                WHERE $whereSql
                ORDER BY u.created_at DESC
                LIMIT :limit OFFSET :offset";

        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();

        return [
            'data' => $stmt->fetchAll(),
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'total_pages' => (int)ceil($total / $limit)
            ]
        ];
    }

    public function getStaffById($id) {
        $rolesStr = implode(',', self::STAFF_ROLES);
        $stmt = $this->db->prepare("SELECT u.id, u.name, u.email, u.role_id, r.name AS role_name, u.is_active, u.created_at, u.updated_at FROM users u JOIN roles r ON u.role_id = r.id WHERE u.id = :id AND u.deleted_at IS NULL AND u.role_id IN ($rolesStr) LIMIT 1");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

    public function updateStaff($id, $data) {
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
            $params['password_hash'] = $data['password_hash'];
        }

        if (empty($fields)) {
            return false;
        }

        $sql = 'UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = :id AND deleted_at IS NULL';
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    public function updateStaffStatus($id, $status) {
        $stmt = $this->db->prepare('UPDATE users SET is_active = :status WHERE id = :id AND deleted_at IS NULL');
        return $stmt->execute(['status' => $status, 'id' => $id]);
    }

    public function softDeleteStaff($id) {
        $stmt = $this->db->prepare('UPDATE users SET deleted_at = CURRENT_TIMESTAMP, is_active = 0 WHERE id = :id AND deleted_at IS NULL');
        return $stmt->execute(['id' => $id]);
    }

    public function validateRole($roleId) {
        $stmt = $this->db->prepare('SELECT id FROM roles WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $roleId]);
        $role = $stmt->fetch();

        if (!$role) {
            return false;
        }

        return in_array($roleId, self::STAFF_ROLES, true);
    }

    public function emailExists($email, $excludeId = null) {
        $sql = 'SELECT COUNT(*) FROM users WHERE email = :email AND deleted_at IS NULL';
        $params = ['email' => $email];

        if ($excludeId !== null) {
            $sql .= ' AND id != :exclude_id';
            $params['exclude_id'] = $excludeId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn() > 0;
    }
}

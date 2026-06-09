<?php

namespace App\Services;

use App\Repositories\UserRepository;
use App\Security\Hash;
use App\Helpers\Validator;
use App\Config\Roles;
use Exception;

class UserService {
    private $userRepo;

    public function __construct() {
        $this->userRepo = new UserRepository();
    }

    public function listUsers($tenantId, $page = 1, $limit = 10, $name = null, $email = null) {
        $page = max(1, (int)$page);
        $limit = max(1, (int)$limit);
        return $this->userRepo->getUsers($tenantId, $page, $limit, $name, $email);
    }

    public function getUser($id, $tenantId) {
        $user = $this->userRepo->findByIdWithTenant($id, $tenantId);
        if (!$user) {
            throw new Exception('User not found', 404);
        }
        return $user;
    }

    public function createUser($data, $tenantId) {
        $this->ensureAdmin();

        if (!Validator::required($data, ['name', 'email', 'password', 'role'])) {
            throw new Exception('Name, email, password, and role are required', 400);
        }

        if (!Validator::email($data['email'])) {
            throw new Exception('Invalid email format', 400);
        }

        if (!Validator::minLength($data['password'], 8)) {
            throw new Exception('Password must be at least 8 characters long', 400);
        }

        $role = (int)$data['role'];
        if (!$this->isValidRole($role)) {
            throw new Exception('Invalid role specified', 400);
        }

        if ($this->userRepo->emailExists($data['email'])) {
            throw new Exception('Email already exists', 409);
        }

        $passwordHash = Hash::make($data['password']);
        $userId = $this->userRepo->create($data['email'], $passwordHash, $role, $tenantId, 1, $data['name']);

        return $userId;
    }

    public function updateUser($id, $data, $tenantId) {
        $this->ensureAdmin();

        // Ensure user exists in this tenant
        $this->getUser($id, $tenantId);

        if (!Validator::required($data, ['name', 'email', 'role'])) {
            throw new Exception('Name, email, and role are required', 400);
        }

        if (!Validator::email($data['email'])) {
            throw new Exception('Invalid email format', 400);
        }

        $role = (int)$data['role'];
        if (!$this->isValidRole($role)) {
            throw new Exception('Invalid role specified', 400);
        }

        if ($this->userRepo->emailExists($data['email'], $id)) {
            throw new Exception('Email already exists', 409);
        }

        $updateData = [
            'name' => $data['name'],
            'email' => $data['email'],
            'role_id' => $role
        ];

        return $this->userRepo->update($id, $tenantId, $updateData);
    }

    public function deleteUser($id, $tenantId, $authenticatedUserId) {
        if ($id == $authenticatedUserId) {
            throw new Exception('Cannot delete your own account', 400);
        }

        // Ensure user exists in this tenant
        $this->getUser($id, $tenantId);

        return $this->userRepo->softDelete($id, $tenantId);
    }

    private function isValidRole($role) {
        $validRoles = [
            Roles::ADMIN,
            Roles::PROVIDER,
            Roles::NURSE,
            Roles::PATIENT,
            Roles::PHARMACIST,
            Roles::RECEPTIONIST
        ];
        return in_array($role, $validRoles, true);
    }

    private function ensureAdmin() {
        $currentRole = $_SESSION['current_role_id'] ?? null;
        if ($currentRole !== Roles::ADMIN) {
            throw new Exception('Forbidden - only ADMIN can assign or update roles', 403);
        }
    }
}

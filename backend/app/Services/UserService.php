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

    public function listUsers($page = 1, $limit = 10, $name = null, $email = null, $excludeRole = null, $roleId = null) {
        $page = max(1, (int)$page);
        $limit = max(1, (int)$limit);
        return $this->userRepo->getUsers($page, $limit, $name, $email, $excludeRole, $roleId);
    }

    public function getUser($id) {
        $user = $this->userRepo->findByIdWithTenant($id);
        if (!$user) {
            throw new Exception('User not found', 404);
        }
        return $user;
    }

    public function createUser($data) {
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
        $userId = $this->userRepo->create($data['email'], $passwordHash, $role, 1, $data['name']);

        return $userId;
    }

    public function updateUser($id, $data) {
        $this->ensureAdmin();

        // Ensure user exists in this tenant
        $this->getUser($id);

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

        return $this->userRepo->update($id, $updateData);
    }

    public function deleteUser($id, $authenticatedUserId) {
        if ($id == $authenticatedUserId) {
            throw new Exception('Cannot delete your own account', 400);
        }

        // Ensure user exists in this tenant
        $this->getUser($id);

        return $this->userRepo->softDelete($id);
    }

    public function activateUser($id) {
        $this->ensureAdmin();
        $this->getUser($id);
        if (!$this->userRepo->toggleActive($id, 1)) {
            throw new Exception('Unable to activate user', 400);
        }
        return true;
    }

    public function deactivateUser($id) {
        $this->ensureAdmin();
        $this->getUser($id);
        if (!$this->userRepo->toggleActive($id, 0)) {
            throw new Exception('Unable to deactivate user', 400);
        }
        return true;
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

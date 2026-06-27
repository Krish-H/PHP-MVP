<?php

namespace App\Services;

use App\Repositories\StaffRepository;
use App\Helpers\Validator;
use App\Security\Hash;
use App\Config\Roles;
use Exception;

class StaffService {
    private $staffRepo;

    private const STAFF_ROLES = [
        Roles::PROVIDER,
        Roles::NURSE,
        Roles::PHARMACIST,
        Roles::RECEPTIONIST
    ];

    public function __construct() {
        $this->staffRepo = new StaffRepository();
    }

    public function getStaffList($page = 1, $limit = 20, $roleId = null) {
        return $this->staffRepo->getStaffList($page, $limit, $roleId);
    }

    public function getStaffById($id) {
        $staff = $this->staffRepo->getStaffById($id);
        if (!$staff) {
            throw new Exception('Staff member not found', 404);
        }

        return $staff;
    }

    public function createStaff($data) {
        $this->ensureAdmin();

        if (!Validator::required($data, ['name', 'email', 'password', 'role_id'])) {
            throw new Exception('Name, email, password and role_id are required', 400);
        }

        if (!Validator::email($data['email'])) {
            throw new Exception('Invalid email format', 400);
        }

        if (!Validator::minLength($data['password'], 8)) {
            throw new Exception('Password must be at least 8 characters long', 400);
        }

        $roleId = (int)$data['role_id'];
        if (!$this->staffRepo->validateRole($roleId)) {
            throw new Exception('Invalid staff role assignment', 400);
        }

        if ($this->staffRepo->emailExists($data['email'])) {
            throw new Exception('Email already exists for this tenant', 409);
        }

        $passwordHash = Hash::make($data['password']);
        return $this->staffRepo->createStaff($data['name'], $data['email'], $passwordHash, $roleId, 1);
    }

    public function updateStaff($id, $data) {
        $this->ensureAdmin();

        $this->getStaffById($id);

        if (empty($data) || (!isset($data['name']) && !isset($data['email']) && !isset($data['role_id']) && !isset($data['password']))) {
            throw new Exception('At least one update field is required', 400);
        }

        if (isset($data['email']) && !Validator::email($data['email'])) {
            throw new Exception('Invalid email format', 400);
        }

        if (isset($data['password']) && !Validator::minLength($data['password'], 8)) {
            throw new Exception('Password must be at least 8 characters long', 400);
        }

        if (isset($data['role_id'])) {
            $roleId = (int)$data['role_id'];
            if (!$this->staffRepo->validateRole($roleId)) {
                throw new Exception('Invalid staff role assignment', 400);
            }
        }

        if (isset($data['email']) && $this->staffRepo->emailExists($data['email'], $id)) {
            throw new Exception('Email already exists for this tenant', 409);
        }

        if (isset($data['password'])) {
            $data['password_hash'] = Hash::make($data['password']);
            unset($data['password']);
        }

        $updated = $this->staffRepo->updateStaff($id, $data);
        if (!$updated) {
            throw new Exception('Unable to update staff member', 400);
        }

        return true;
    }

    public function activateStaff($id) {
        $this->ensureAdmin();
        $this->getStaffById($id);

        if (!$this->staffRepo->updateStaffStatus($id, 1)) {
            throw new Exception('Unable to activate staff member', 400);
        }

        return true;
    }

    public function deactivateStaff($id) {
        $this->ensureAdmin();
        $this->getStaffById($id);

        if (!$this->staffRepo->updateStaffStatus($id, 0)) {
            throw new Exception('Unable to deactivate staff member', 400);
        }

        return true;
    }

    public function softDeleteStaff($id, $authenticatedUserId) {
        $this->ensureAdmin();

        if ($authenticatedUserId === null) {
            throw new Exception('Unauthorized', 401);
        }

        if ($authenticatedUserId == $id) {
            throw new Exception('Cannot delete your own staff account', 400);
        }

        $this->getStaffById($id);

        if (!$this->staffRepo->softDeleteStaff($id)) {
            throw new Exception('Unable to delete staff member', 400);
        }

        return true;
    }

    private function ensureAdmin() {
        $currentRole = $_SESSION['current_role_id'] ?? null;
        if ($currentRole !== Roles::ADMIN) {
            throw new Exception('Forbidden - admin access required', 403);
        }
    }
}

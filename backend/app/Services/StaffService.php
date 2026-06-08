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

    public function getStaffList($tenantId, $page = 1, $limit = 20, $roleId = null) {
        if (!$tenantId) {
            throw new Exception('Unauthorized - tenant missing', 401);
        }

        return $this->staffRepo->getStaffList($tenantId, $page, $limit, $roleId);
    }

    public function getStaffById($id, $tenantId) {
        if (!$tenantId) {
            throw new Exception('Unauthorized - tenant missing', 401);
        }

        $staff = $this->staffRepo->getStaffById($id, $tenantId);
        if (!$staff) {
            throw new Exception('Staff member not found', 404);
        }

        return $staff;
    }

    public function createStaff($data, $tenantId) {
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

        if ($this->staffRepo->emailExists($data['email'], $tenantId)) {
            throw new Exception('Email already exists for this tenant', 409);
        }

        $passwordHash = Hash::make($data['password']);
        return $this->staffRepo->createStaff($tenantId, $data['name'], $data['email'], $passwordHash, $roleId, 1);
    }

    public function updateStaff($id, $data, $tenantId) {
        $this->ensureAdmin();

        $this->getStaffById($id, $tenantId);

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

        if (isset($data['email']) && $this->staffRepo->emailExists($data['email'], $tenantId, $id)) {
            throw new Exception('Email already exists for this tenant', 409);
        }

        if (isset($data['password'])) {
            $data['password_hash'] = Hash::make($data['password']);
            unset($data['password']);
        }

        $updated = $this->staffRepo->updateStaff($id, $tenantId, $data);
        if (!$updated) {
            throw new Exception('Unable to update staff member', 400);
        }

        return true;
    }

    public function activateStaff($id, $tenantId) {
        $this->ensureAdmin();
        $this->getStaffById($id, $tenantId);

        if (!$this->staffRepo->updateStaffStatus($id, $tenantId, 1)) {
            throw new Exception('Unable to activate staff member', 400);
        }

        return true;
    }

    public function deactivateStaff($id, $tenantId) {
        $this->ensureAdmin();
        $this->getStaffById($id, $tenantId);

        if (!$this->staffRepo->updateStaffStatus($id, $tenantId, 0)) {
            throw new Exception('Unable to deactivate staff member', 400);
        }

        return true;
    }

    public function softDeleteStaff($id, $tenantId, $authenticatedUserId) {
        $this->ensureAdmin();

        if ($authenticatedUserId === null) {
            throw new Exception('Unauthorized', 401);
        }

        if ($authenticatedUserId == $id) {
            throw new Exception('Cannot delete your own staff account', 400);
        }

        $this->getStaffById($id, $tenantId);

        if (!$this->staffRepo->softDeleteStaff($id, $tenantId)) {
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

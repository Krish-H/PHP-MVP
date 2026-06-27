<?php

namespace App\Controllers;

use App\Helpers\Request;
use App\Helpers\Response;
use App\Services\StaffService;
use Exception;

class DoctorController {
    private $staffService;

    public function __construct() {
        $this->staffService = new StaffService();
    }

    public function index() {
        try {
            $tenantId = $_SESSION['current_tenant_id'] ?? null;
            if (!$tenantId) {
                Response::json(['error' => 'Unauthorized - tenant isolation missing'], 401);
            }

            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
            $roleId = isset($_GET['role_id']) ? (int)$_GET['role_id'] : null;

            $result = $this->staffService->getStaffList($page, $limit, $roleId);
            Response::json($result, 200);
        } catch (Exception $e) {
            $statusCode = (is_numeric($e->getCode()) && $e->getCode() >= 100 && $e->getCode() < 600) ? $e->getCode() : 500;
            Response::json(['error' => $e->getMessage()], $statusCode);
        }
    }

    public function show($params) {
        try {
            $tenantId = $_SESSION['current_tenant_id'] ?? null;
            if (!$tenantId) {
                Response::json(['error' => 'Unauthorized - tenant isolation missing'], 401);
            }

            $staff = $this->staffService->getStaffById($params['id']);
            Response::json(['staff' => $staff], 200);
        } catch (Exception $e) {
            $statusCode = (is_numeric($e->getCode()) && $e->getCode() >= 100 && $e->getCode() < 600) ? $e->getCode() : 500;
            Response::json(['error' => $e->getMessage()], $statusCode);
        }
    }

    public function store() {
        try {
            $tenantId = $_SESSION['current_tenant_id'] ?? null;
            if (!$tenantId) {
                Response::json(['error' => 'Unauthorized - tenant isolation missing'], 401);
            }

            $data = Request::body();
            if (!is_array($data)) {
                Response::json(['error' => 'Invalid request body'], 400);
            }

            $staffId = $this->staffService->createStaff($data);
            Response::json(['message' => 'Staff created successfully', 'staff_id' => $staffId], 201);
        } catch (Exception $e) {
            $statusCode = (is_numeric($e->getCode()) && $e->getCode() >= 100 && $e->getCode() < 600) ? $e->getCode() : 500;
            Response::json(['error' => $e->getMessage()], $statusCode);
        }
    }

    public function update($params) {
        try {
            $tenantId = $_SESSION['current_tenant_id'] ?? null;
            if (!$tenantId) {
                Response::json(['error' => 'Unauthorized - tenant isolation missing'], 401);
            }

            $data = Request::body();
            if (!is_array($data)) {
                Response::json(['error' => 'Invalid request body'], 400);
            }

            $this->staffService->updateStaff($params['id'], $data);
            Response::json(['message' => 'Staff updated successfully'], 200);
        } catch (Exception $e) {
            $statusCode = (is_numeric($e->getCode()) && $e->getCode() >= 100 && $e->getCode() < 600) ? $e->getCode() : 500;
            Response::json(['error' => $e->getMessage()], $statusCode);
        }
    }

    public function activate($params) {
        try {
            $tenantId = $_SESSION['current_tenant_id'] ?? null;
            if (!$tenantId) {
                Response::json(['error' => 'Unauthorized - tenant isolation missing'], 401);
            }

            $this->staffService->activateStaff($params['id']);
            Response::json(['message' => 'Staff activated successfully'], 200);
        } catch (Exception $e) {
            $statusCode = (is_numeric($e->getCode()) && $e->getCode() >= 100 && $e->getCode() < 600) ? $e->getCode() : 500;
            Response::json(['error' => $e->getMessage()], $statusCode);
        }
    }

    public function deactivate($params) {
        try {
            $tenantId = $_SESSION['current_tenant_id'] ?? null;
            if (!$tenantId) {
                Response::json(['error' => 'Unauthorized - tenant isolation missing'], 401);
            }

            $this->staffService->deactivateStaff($params['id']);
            Response::json(['message' => 'Staff deactivated successfully'], 200);
        } catch (Exception $e) {
            $statusCode = (is_numeric($e->getCode()) && $e->getCode() >= 100 && $e->getCode() < 600) ? $e->getCode() : 500;
            Response::json(['error' => $e->getMessage()], $statusCode);
        }
    }

    public function destroy($params) {
        try {
            $tenantId = $_SESSION['current_tenant_id'] ?? null;
            $authenticatedUserId = $_SESSION['current_user_id'] ?? null;

            if (!$tenantId || !$authenticatedUserId) {
                Response::json(['error' => 'Unauthorized - tenant isolation missing'], 401);
            }

            $this->staffService->softDeleteStaff($params['id'], $authenticatedUserId);
            Response::json(['message' => 'Staff deleted successfully'], 200);
        } catch (Exception $e) {
            $statusCode = (is_numeric($e->getCode()) && $e->getCode() >= 100 && $e->getCode() < 600) ? $e->getCode() : 500;
            Response::json(['error' => $e->getMessage()], $statusCode);
        }
    }
}

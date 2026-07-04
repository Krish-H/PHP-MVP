<?php

namespace App\Controllers;

use App\Helpers\Request;
use App\Helpers\Response;
use App\Services\UserService;
use Exception;

class UserController {
    private $userService;

    public function __construct() {
        $this->userService = new UserService();
    }

    public function index() {
        try {
            $tenantId = $_SESSION['current_tenant_id'] ?? null;
            if (!$tenantId) {
                Response::json(['error' => 'Unauthorized - tenant isolation missing'], 401);
            }

            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
            $name = isset($_GET['name']) ? $_GET['name'] : null;
            $email = isset($_GET['email']) ? $_GET['email'] : null;
            $excludeRole = isset($_GET['exclude_role']) ? (int)$_GET['exclude_role'] : null;
            $roleId = isset($_GET['role_id']) ? (int)$_GET['role_id'] : null;

            $result = $this->userService->listUsers($page, $limit, $name, $email, $excludeRole, $roleId);

            Response::json($result, 200);
        } catch (Exception $e) {
            $code = $e->getCode();
            $statusCode = (is_numeric($code) && $code >= 100 && $code < 600) ? (int)$code : 500;
            Response::json(['error' => $e->getMessage()], $statusCode);
        }
    }

    public function show($params) {
        try {
            $tenantId = $_SESSION['current_tenant_id'] ?? null;
            if (!$tenantId) {
                Response::json(['error' => 'Unauthorized'], 401);
            }

            $user = $this->userService->getUser($params['id']);
            unset($user['password_hash']); // Security best practice: don't return password hash

            Response::json(['user' => $user], 200);
        } catch (Exception $e) {
            $code = $e->getCode();
            $statusCode = (is_numeric($code) && $code >= 100 && $code < 600) ? (int)$code : 500;
            Response::json(['error' => $e->getMessage()], $statusCode);
        }
    }

    public function store() {
        try {
            $tenantId = $_SESSION['current_tenant_id'] ?? null;
            if (!$tenantId) {
                Response::json(['error' => 'Unauthorized'], 401);
            }

            $data = Request::body();
            if (!is_array($data)) {
                Response::json(['error' => 'Invalid request body'], 400);
            }

            $userId = $this->userService->createUser($data);

            Response::json([
                'message' => 'User created successfully',
                'user_id' => $userId
            ], 201);
        } catch (Exception $e) {
            $code = $e->getCode();
            $statusCode = (is_numeric($code) && $code >= 100 && $code < 600) ? (int)$code : 500;
            Response::json(['error' => $e->getMessage()], $statusCode);
        }
    }

    public function update($params) {
        try {
            $tenantId = $_SESSION['current_tenant_id'] ?? null;
            if (!$tenantId) {
                Response::json(['error' => 'Unauthorized'], 401);
            }

            $data = Request::body();
            if (!is_array($data)) {
                Response::json(['error' => 'Invalid request body'], 400);
            }

            $this->userService->updateUser($params['id'], $data);

            Response::json([
                'message' => 'User updated successfully'
            ], 200);
        } catch (Exception $e) {
            $code = $e->getCode();
            $statusCode = (is_numeric($code) && $code >= 100 && $code < 600) ? (int)$code : 500;
            Response::json(['error' => $e->getMessage()], $statusCode);
        }
    }

    public function destroy($params) {
        try {
            $tenantId = $_SESSION['current_tenant_id'] ?? null;
            $authenticatedUserId = $_SESSION['current_user_id'] ?? null;

            if (!$tenantId || !$authenticatedUserId) {
                Response::json(['error' => 'Unauthorized'], 401);
            }

            $this->userService->deleteUser($params['id'], $authenticatedUserId);

            Response::json([
                'message' => 'User deleted successfully'
            ], 200);
        } catch (Exception $e) {
            $code = $e->getCode();
            $statusCode = (is_numeric($code) && $code >= 100 && $code < 600) ? (int)$code : 500;
            Response::json(['error' => $e->getMessage()], $statusCode);
        }
    }

    public function activate($params) {
        try {
            $tenantId = $_SESSION['current_tenant_id'] ?? null;
            if (!$tenantId) {
                Response::json(['error' => 'Unauthorized'], 401);
            }

            $this->userService->activateUser($params['id']);
            Response::json(['message' => 'User activated successfully'], 200);
        } catch (Exception $e) {
            $code = $e->getCode();
            $statusCode = (is_numeric($code) && $code >= 100 && $code < 600) ? (int)$code : 500;
            Response::json(['error' => $e->getMessage()], $statusCode);
        }
    }

    public function deactivate($params) {
        try {
            $tenantId = $_SESSION['current_tenant_id'] ?? null;
            if (!$tenantId) {
                Response::json(['error' => 'Unauthorized'], 401);
            }

            $this->userService->deactivateUser($params['id']);
            Response::json(['message' => 'User deactivated successfully'], 200);
        } catch (Exception $e) {
            $code = $e->getCode();
            $statusCode = (is_numeric($code) && $code >= 100 && $code < 600) ? (int)$code : 500;
            Response::json(['error' => $e->getMessage()], $statusCode);
        }
    }
}

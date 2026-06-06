<?php

namespace App\Controllers;

use App\Helpers\Request;
use App\Helpers\Response;
use App\Helpers\Validator;
use App\Repositories\UserRepository;
use App\Services\AuthService;
use Exception;

class DoctorController {
    private $userRepo;
    private $authService;

    public function __construct() {
        $this->userRepo = new UserRepository();
        $this->authService = new AuthService();
    }

    public function index() {
        $staff = $this->userRepo->listStaff($_SESSION['current_tenant_id']);
        Response::json(['staff' => $staff], 200);
    }

    public function store() {
        $data = Request::body();

        if (!is_array($data)) {
            Response::json(['error' => 'Invalid request body'], 400);
        }

        if (!Validator::required($data, ['email', 'password', 'role_id'])) {
            Response::json(['error' => 'Email, password, and role_id are required'], 400);
        }

        try {
            if ($this->userRepo->findByEmail($data['email'])) {
                throw new Exception('User already exists', 409);
            }

            $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
            $userId = $this->userRepo->create($data['email'], $hashedPassword, $data['role_id'], $_SESSION['current_tenant_id'], 1);

            Response::json(['message' => 'Staff created', 'user_id' => $userId], 201);
        } catch (Exception $e) {
            Response::json(['error' => $e->getMessage()], $e->getCode() ?: 500);
        }
    }

    public function update($params) {
        $data = Request::body();

        if (!is_array($data)) {
            Response::json(['error' => 'Invalid request body'], 400);
        }

        try {
            $updated = $this->userRepo->update($params['id'], $_SESSION['current_tenant_id'], $data);
            if (!$updated) {
                throw new Exception('Unable to update staff', 400);
            }
            Response::json(['message' => 'Staff updated'], 200);
        } catch (Exception $e) {
            Response::json(['error' => $e->getMessage()], $e->getCode() ?: 500);
        }
    }

    public function destroy($params) {
        try {
            if (!$this->userRepo->toggleActive($params['id'], $_SESSION['current_tenant_id'], 0)) {
                throw new Exception('Unable to disable staff', 400);
            }
            Response::json(['message' => 'Staff disabled'], 200);
        } catch (Exception $e) {
            Response::json(['error' => $e->getMessage()], $e->getCode() ?: 500);
        }
    }
}

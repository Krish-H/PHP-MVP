<?php

namespace App\Controllers;

use App\Helpers\Request;
use App\Helpers\Response;
use App\Helpers\Validator;
use App\Services\PatientService;
use Exception;

class PatientController {
    private $patientService;

    public function __construct() {
        $this->patientService = new PatientService();
    }

    public function index() {
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
        $search = isset($_GET['search']) ? $_GET['search'] : '';
        $gender = isset($_GET['gender']) ? $_GET['gender'] : '';
        $status = isset($_GET['status']) ? $_GET['status'] : '';

        $result = $this->patientService->listPatients($page, $limit, $search, $gender, $status);
        Response::json([
            'patients' => $result['data'],
            'total' => $result['total']
        ], 200);
    }

    public function patientUsers() {
        try {
            $users = $this->patientService->getAvailablePatientUsers();
            Response::json(['users' => $users], 200);
        } catch (Exception $e) {
            Response::json(['error' => $e->getMessage()], $e->getCode() ?: 500);
        }
    }

    public function stats() {
        try {
            $stats = $this->patientService->getPatientStats();
            Response::json(['stats' => $stats], 200);
        } catch (Exception $e) {
            Response::json(['error' => $e->getMessage()], $e->getCode() ?: 500);
        }
    }

    public function store() {
        $data = Request::body();

        if (!is_array($data)) {
            Response::json(['error' => 'Invalid request body'], 400);
        }

        try {
            $patientId = $this->patientService->addPatient($data, $_SESSION['current_user_id']);
            Response::json(['message' => 'Patient added successfully', 'patient_id' => $patientId], 201);
        } catch (Exception $e) {
            Response::json(['error' => $e->getMessage()], $e->getCode() ?: 500);
        }
    }

    public function show($params) {
        try {
            $patient = $this->patientService->getPatient($params['id']);
            Response::json(['patient' => $patient], 200);
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
            $this->patientService->updatePatient($params['id'], $data);
            Response::json(['message' => 'Patient updated successfully'], 200);
        } catch (Exception $e) {
            Response::json(['error' => $e->getMessage()], $e->getCode() ?: 500);
        }
    }

    public function destroy($params) {
        try {
            $this->patientService->deletePatient($params['id']);
            Response::json(['message' => 'Patient deleted successfully'], 200);
        } catch (Exception $e) {
            Response::json(['error' => $e->getMessage()], $e->getCode() ?: 500);
        }
    }
}

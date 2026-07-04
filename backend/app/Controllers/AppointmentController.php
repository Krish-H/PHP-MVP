<?php

namespace App\Controllers;

use App\Helpers\Request;
use App\Helpers\Response;
use App\Services\AppointmentService;
use Exception;

class AppointmentController {
    private $appointmentService;

    public function __construct() {
        $this->appointmentService = new AppointmentService();
    }

    public function index() {
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
        $status = isset($_GET['status']) ? $_GET['status'] : '';

        $result = $this->appointmentService->listAppointments($page, $limit, $status);
        Response::json([
            'appointments' => $result['data'],
            'total' => $result['total']
        ], 200);
    }

    public function store() {
        $data = Request::body();

        if (!is_array($data)) {
            Response::json(['error' => 'Invalid request body'], 400);
        }

        try {
            $appointmentId = $this->appointmentService->createAppointment($data);
            Response::json(['message' => 'Appointment created', 'appointment_id' => $appointmentId], 201);
        } catch (Exception $e) {
            Response::json(['error' => $e->getMessage()], $e->getCode() ?: 500);
        }
    }

    public function show($params) {
        try {
            $appointment = $this->appointmentService->getAppointment($params['id']);
            Response::json(['appointment' => $appointment], 200);
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
            $this->appointmentService->updateAppointment($params['id'], $data);
            Response::json(['message' => 'Appointment updated'], 200);
        } catch (Exception $e) {
            Response::json(['error' => $e->getMessage()], $e->getCode() ?: 500);
        }
    }

    public function destroy($params) {
        try {
            $this->appointmentService->cancelAppointment($params['id']);
            Response::json(['message' => 'Appointment cancelled'], 200);
        } catch (Exception $e) {
            Response::json(['error' => $e->getMessage()], $e->getCode() ?: 500);
        }
    }
}

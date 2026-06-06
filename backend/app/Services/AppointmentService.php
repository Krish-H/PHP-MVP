<?php

namespace App\Services;

use App\Repositories\AppointmentRepository;
use App\Repositories\PatientRepository;
use App\Config\Roles;
use Exception;

class AppointmentService {

    private $appointmentRepo;
    private $patientRepo;

    public function __construct() {
        $this->appointmentRepo = new AppointmentRepository();
        $this->patientRepo     = new PatientRepository();
    }

    // ----------------------------------------------------------------
    // Called by AppointmentController@index
    // GET /api/appointments
    // Role-based: PROVIDER/NURSE → by provider_id
    //             PATIENT        → by patient_id
    //             ADMIN          → all for tenant
    // ----------------------------------------------------------------
    public function listAppointments($tenantId) {
        $userId = $_SESSION['current_user_id'] ?? null;
        $roleId = $_SESSION['current_role_id'] ?? null;

        // PROVIDER or NURSE — see appointments assigned to them
        if (in_array($roleId, [Roles::PROVIDER, Roles::NURSE])) {
            return $this->appointmentRepo->findByProvider((int) $tenantId, (int) $userId);
        }

        // PATIENT — see only their own appointments
        if ($roleId === Roles::PATIENT) {
            $patient = $this->patientRepo->findByUserId((int) $userId, (int) $tenantId);

            if (!$patient) {
                throw new Exception('Patient profile not found', 404);
            }

            return $this->appointmentRepo->findByPatient((int) $tenantId, (int) $patient['id']);
        }

        // ADMIN — all appointments for the tenant
        return $this->appointmentRepo->findAll((int) $tenantId);
    }

    // ----------------------------------------------------------------
    // Called by AppointmentController@show
    // GET /api/appointments/{id}
    // ----------------------------------------------------------------
    public function getAppointment($id, $tenantId) {
        $appointment = $this->appointmentRepo->findById((int) $id, (int) $tenantId);

        if (!$appointment) {
            throw new Exception('Appointment not found', 404);
        }

        return $appointment;
    }

    // ----------------------------------------------------------------
    // Called by AppointmentController@store
    // POST /api/appointments
    // ----------------------------------------------------------------
    public function createAppointment($data, $tenantId) {
        // Validate required fields
        $required = ['patient_id', 'provider_id', 'appointment_date', 'appointment_time'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new Exception("Field '$field' is required", 422);
            }
        }

        // Check provider slot is not already booked
        $slotTaken = $this->appointmentRepo->slotExists(
            (int) $data['provider_id'],
            $data['appointment_date'],
            $data['appointment_time']
        );

        if ($slotTaken) {
            throw new Exception('This time slot is already booked for the provider', 409);
        }

        return $this->appointmentRepo->create($data, (int) $tenantId);
    }

    // ----------------------------------------------------------------
    // Called by AppointmentController@update
    // PUT /api/appointments/{id}
    // ----------------------------------------------------------------
    public function updateAppointment($id, $data, $tenantId) {
        $existing = $this->appointmentRepo->findById((int) $id, (int) $tenantId);

        if (!$existing) {
            throw new Exception('Appointment not found', 404);
        }

        // Re-check slot only if provider/date/time is being changed
        $providerChanged = isset($data['provider_id'])      && $data['provider_id']      != $existing['provider_id'];
        $dateChanged     = isset($data['appointment_date']) && $data['appointment_date']  != $existing['appointment_date'];
        $timeChanged     = isset($data['appointment_time']) && $data['appointment_time']  != $existing['appointment_time'];

        if ($providerChanged || $dateChanged || $timeChanged) {
            $checkProvider = $data['provider_id']      ?? $existing['provider_id'];
            $checkDate     = $data['appointment_date'] ?? $existing['appointment_date'];
            $checkTime     = $data['appointment_time'] ?? $existing['appointment_time'];

            $slotTaken = $this->appointmentRepo->slotExists(
                (int) $checkProvider,
                $checkDate,
                $checkTime,
                (int) $id  // exclude current appointment from check
            );

            if ($slotTaken) {
                throw new Exception('This time slot is already booked for the provider', 409);
            }
        }

        $updated = $this->appointmentRepo->update((int) $id, (int) $tenantId, $data);

        if (!$updated) {
            throw new Exception('No changes were made', 422);
        }

        return true;
    }

    // ----------------------------------------------------------------
    // Called by AppointmentController@destroy
    // DELETE /api/appointments/{id}
    // ----------------------------------------------------------------
    public function cancelAppointment($id, $tenantId) {
        $existing = $this->appointmentRepo->findById((int) $id, (int) $tenantId);

        if (!$existing) {
            throw new Exception('Appointment not found', 404);
        }

        if ($existing['is_cancelled']) {
            throw new Exception('Appointment is already cancelled', 409);
        }

        $cancelled = $this->appointmentRepo->cancel((int) $id, (int) $tenantId);

        if (!$cancelled) {
            throw new Exception('Appointment could not be cancelled', 500);
        }

        return true;
    }
}
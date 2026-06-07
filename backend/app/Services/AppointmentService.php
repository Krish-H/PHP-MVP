<?php

namespace App\Services;

use App\Repositories\AppointmentRepository;
<<<<<<< Updated upstream
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
=======
use Exception;

class AppointmentService {
    private $appointmentRepo;

    /** Valid status transitions: current status => allowed next statuses */
    private const STATUS_TRANSITIONS = [
        'scheduled'  => ['completed', 'cancelled'],
        'completed'  => [],
        'cancelled'  => [],
    ];

    public function __construct() {
        $this->appointmentRepo = new AppointmentRepository();
    }

    // ----------------------------------------------------------------
    // Public API
    // ----------------------------------------------------------------

    public function createAppointment(array $data, int $tenantId): int {
        $this->validateRequired($data, ['patient_id', 'provider_id', 'appointment_date', 'appointment_time']);
        $this->validateDateTimeFormat($data['appointment_date'], $data['appointment_time']);

        if ($this->appointmentRepo->hasConflict(
            (int) $data['provider_id'],
            $data['appointment_date'],
            $data['appointment_time']
        )) {
            throw new Exception('Provider already has an appointment at this date and time', 409);
        }

        return $this->appointmentRepo->create($data, $tenantId);
    }

    public function getAppointment(int $id, int $tenantId): array {
        $appointment = $this->appointmentRepo->findById($id, $tenantId);
>>>>>>> Stashed changes

        if (!$appointment) {
            throw new Exception('Appointment not found', 404);
        }

        return $appointment;
    }

<<<<<<< Updated upstream
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
=======
    public function listAppointments(int $tenantId): array {
        return $this->appointmentRepo->findAll($tenantId);
    }

    public function updateAppointment(int $id, array $data, int $tenantId): void {
        $existing = $this->appointmentRepo->findById($id, $tenantId);
>>>>>>> Stashed changes

        if (!$existing) {
            throw new Exception('Appointment not found', 404);
        }

        if ($existing['is_cancelled']) {
<<<<<<< Updated upstream
            throw new Exception('Appointment is already cancelled', 409);
        }

        $cancelled = $this->appointmentRepo->cancel((int) $id, (int) $tenantId);

        if (!$cancelled) {
            throw new Exception('Appointment could not be cancelled', 500);
        }

        return true;
    }
}
=======
            throw new Exception('Cannot update a cancelled appointment', 400);
        }

        if ($existing['status'] === 'completed') {
            throw new Exception('Cannot update a completed appointment', 400);
        }

        // Validate status transition if status is being changed
        if (isset($data['status'])) {
            $this->validateStatusTransition($existing['status'], $data['status']);
        }

        // Re-check slot conflict if date, time, or provider is changing
        $providerId = isset($data['provider_id']) ? (int) $data['provider_id'] : (int) $existing['provider_id'];
        $date       = $data['appointment_date'] ?? $existing['appointment_date'];
        $time       = $data['appointment_time'] ?? $existing['appointment_time'];

        if (isset($data['appointment_date']) || isset($data['appointment_time']) || isset($data['provider_id'])) {
            $this->validateDateTimeFormat($date, $time);

            if ($this->appointmentRepo->hasConflict($providerId, $date, $time, $id)) {
                throw new Exception('Provider already has an appointment at this date and time', 409);
            }
        }

        $updated = $this->appointmentRepo->update($id, $data, $tenantId);

        if (!$updated) {
            throw new Exception('No changes were made', 400);
        }
    }

    public function cancelAppointment(int $id, int $tenantId): void {
        $existing = $this->appointmentRepo->findById($id, $tenantId);

        if (!$existing) {
            throw new Exception('Appointment not found', 404);
        }

        if ($existing['is_cancelled']) {
            throw new Exception('Appointment is already cancelled', 400);
        }

        if ($existing['status'] === 'completed') {
            throw new Exception('Cannot cancel a completed appointment', 400);
        }

        $cancelled = $this->appointmentRepo->cancel($id, $tenantId);

        if (!$cancelled) {
            throw new Exception('Failed to cancel appointment', 500);
        }
    }

    // ----------------------------------------------------------------
    // Private helpers
    // ----------------------------------------------------------------

    private function validateRequired(array $data, array $required): void {
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new Exception("Missing required field: $field", 422);
            }
        }
    }

    private function validateDateTimeFormat(string $date, string $time): void {
        $d = \DateTime::createFromFormat('Y-m-d', $date);
        if (!$d || $d->format('Y-m-d') !== $date) {
            throw new Exception('Invalid date format. Use YYYY-MM-DD', 422);
        }

        $t = \DateTime::createFromFormat('H:i:s', $time);
        if (!$t) {
            $t = \DateTime::createFromFormat('H:i', $time);
        }
        if (!$t) {
            throw new Exception('Invalid time format. Use HH:MM or HH:MM:SS', 422);
        }
    }

    /**
     * Enforce allowed status transitions.
     */
    private function validateStatusTransition(string $current, string $next): void {
        $validStatuses = array_keys(self::STATUS_TRANSITIONS);

        if (!in_array($next, $validStatuses, true)) {
            throw new Exception(
                "Invalid status '$next'. Allowed: " . implode(', ', $validStatuses),
                422
            );
        }

        $allowed = self::STATUS_TRANSITIONS[$current] ?? [];

        if (!in_array($next, $allowed, true)) {
            throw new Exception(
                "Cannot transition appointment from '$current' to '$next'",
                400
            );
        }
    }
}
>>>>>>> Stashed changes

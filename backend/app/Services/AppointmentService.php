<?php

namespace App\Services;

use App\Repositories\AppointmentRepository;
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

    public function createAppointment(array $data): int {
        $this->validateRequired($data, ['patient_id', 'provider_id', 'appointment_date', 'appointment_time']);
        $this->validateDateTimeFormat($data['appointment_date'], $data['appointment_time']);

        if ($this->appointmentRepo->slotExists(
            (int) $data['provider_id'],
            $data['appointment_date'],
            $data['appointment_time']
        )) {
            throw new Exception('Provider already has an appointment at this date and time', 409);
        }

        return $this->appointmentRepo->create($data);
    }

    public function getAppointment(int $id): array {
        $appointment = $this->appointmentRepo->findById($id);

        if (!$appointment) {
            throw new Exception('Appointment not found', 404);
        }

        return $appointment;
    }

    public function listAppointments(int $page = 1, int $limit = 10, string $status = ''): array {
        return $this->appointmentRepo->findAll($page, $limit, $status);
    }

    public function updateAppointment(int $id, array $data): void {
        $existing = $this->appointmentRepo->findById($id);

        if (!$existing) {
            throw new Exception('Appointment not found', 404);
        }

        if ($existing['is_cancelled']) {
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

            if ($this->appointmentRepo->slotExists($providerId, $date, $time, $id)) {
                throw new Exception('Provider already has an appointment at this date and time', 409);
            }
        }

        $updated = $this->appointmentRepo->update($id, $data);

        if (!$updated) {
            throw new Exception('No changes were made', 400);
        }
    }

    public function cancelAppointment(int $id): void {
        $existing = $this->appointmentRepo->findById($id);

        if (!$existing) {
            throw new Exception('Appointment not found', 404);
        }

        if ($existing['is_cancelled']) {
            throw new Exception('Appointment is already cancelled', 400);
        }

        if ($existing['status'] === 'completed') {
            throw new Exception('Cannot cancel a completed appointment', 400);
        }

        $cancelled = $this->appointmentRepo->cancel($id);

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

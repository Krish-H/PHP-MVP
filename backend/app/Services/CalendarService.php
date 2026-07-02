<?php

namespace App\Services;

use App\Config\Env;
use App\Config\Roles;
use App\Repositories\CalendarRepository;
use App\Security\AES;
use Exception;

/**
 * CalendarService
 *
 * Business logic for the calendar API.
 *
 * Role-based visibility:
 *   ADMIN (1)        → all appointments in tenant
 *   RECEPTIONIST (7) → all appointments in tenant
 *   NURSE (3)        → all appointments in tenant
 *   DOCTOR (6)       → only appointments where provider_id = their user_id
 */
class CalendarService {

    private $calendarRepo;
    private $aesKey;

    public function __construct() {
        $this->calendarRepo = new CalendarRepository();
        $this->aesKey       = Env::get('AES_KEY');
    }

    // ----------------------------------------------------------------
    // Fetch by date
    // ----------------------------------------------------------------

    public function getByDate(array $params, int $roleId, int $userId): array {
        if (empty($params['date'])) {
            throw new Exception('Missing required parameter: date', 422);
        }

        $this->validateDateFormat($params['date']);

        $providerId = $this->resolveProviderScope($roleId, $userId);

        $rows = $this->calendarRepo->fetchByDate($params['date'], $providerId);

        return $this->formatAppointments($rows);
    }

    // ----------------------------------------------------------------
    // Fetch by range
    // ----------------------------------------------------------------

    public function getByRange(array $params, int $roleId, int $userId): array {
        if (empty($params['start_date']) || empty($params['end_date'])) {
            throw new Exception('Missing required parameters: start_date, end_date', 422);
        }

        $this->validateDateFormat($params['start_date']);
        $this->validateDateFormat($params['end_date']);

        if ($params['start_date'] > $params['end_date']) {
            throw new Exception('start_date must be before or equal to end_date', 422);
        }

        $providerId = $this->resolveProviderScope($roleId, $userId);

        $rows = $this->calendarRepo->fetchByRange(
            $params['start_date'],
            $params['end_date'],
            $providerId
        );

        return $this->formatAppointments($rows);
    }

    // ----------------------------------------------------------------
    // Tooltip
    // ----------------------------------------------------------------

    public function getTooltip(int $id, int $roleId, int $userId): array {
        $row = $this->calendarRepo->fetchTooltip($id);

        if (!$row) {
            throw new Exception('Appointment not found', 404);
        }

        // DOCTOR can only see their own tooltip
        if ($roleId === Roles::PROVIDER && (int) $row['provider_id'] !== $userId) {
            throw new Exception('Access denied', 403);
        }

        // Decrypt patient PHI from joined columns
        $patientName  = !empty($row['patient_name_enc'])  ? AES::decrypt($row['patient_name_enc'],  $this->aesKey) : null;
        $patientPhone = !empty($row['patient_phone_enc']) ? AES::decrypt($row['patient_phone_enc'], $this->aesKey) : null;

        return [
            'id'               => (int) $row['id'],
            'appointment_date' => $row['appointment_date'],
            'appointment_time' => $row['appointment_time'],
            'status'           => $row['status'],
            'notes'            => $row['notes'],
            'provider_id'      => (int) $row['provider_id'],
            'patient'          => [
                'id'    => (int) $row['patient_id'],
                'name'  => $patientName,
                'phone' => $patientPhone,
            ],
        ];
    }

    // ----------------------------------------------------------------
    // Private helpers
    // ----------------------------------------------------------------

    /**
     * Returns the provider_id to scope queries to, or null for all.
     * DOCTOR sees only their own; all other allowed roles see everything.
     */
    private function resolveProviderScope(int $roleId, int $userId): ?int {
        return ($roleId === Roles::PROVIDER) ? $userId : null;
    }

    private function validateDateFormat(string $date): void {
        $d = \DateTime::createFromFormat('Y-m-d', $date);
        if (!$d || $d->format('Y-m-d') !== $date) {
            throw new Exception("Invalid date format '$date'. Use YYYY-MM-DD", 422);
        }
    }

    private function formatAppointments(array $rows): array {
        return array_map(function ($row) {
            $patientName = null;
            if (!empty($row['encrypted_patient_name'])) {
                $decrypted = \App\Security\AES::decrypt($row['encrypted_patient_name'], $this->aesKey);
                $patientName = $decrypted !== false ? $decrypted : null;
            }

            return [
                'id'               => (int) $row['id'],
                'patient_id'       => (int) $row['patient_id'],
                'patient_name'     => $patientName,
                'provider_id'      => (int) $row['provider_id'],
                'provider_name'    => $row['provider_name'] ?? null,
                'appointment_date' => $row['appointment_date'],
                'appointment_time' => $row['appointment_time'],
                'status'           => $row['status'],
                'notes'            => $row['notes'],
            ];
        }, $rows);
    }
}

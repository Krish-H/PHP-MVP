<?php

namespace App\Repositories;

use App\Config\Database;

/**
 * AppointmentRepository
 *
 * Handles all SQL for the `appointments` table.
 *
 * Role-based filtering is done HERE so the Service layer
 * just passes the role and the relevant user/patient/provider ID:
 *
 *   - PROVIDER / NURSE  → sees all appointments where provider_id = $providerId
 *   - PATIENT           → sees only their own appointments where patient_id = $patientId
 *   - ADMIN             → findAll() with no user filter, only tenant scope
 *
 * No PHI is stored in this table — appointments hold IDs and status only.
 */
class AppointmentRepository {

    private $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    // ----------------------------------------------------------------
    // READ
    // ----------------------------------------------------------------

    /**
     * Get all non-cancelled appointments for a tenant (Admin view).
     *
     * @param  int $tenantId
     * @return array
     */
    public function findAll(int $tenantId): array {
        $stmt = $this->db->prepare(
            'SELECT * FROM appointments
             WHERE tenant_id   = :tenant_id
               AND is_cancelled = 0
             ORDER BY appointment_date ASC, appointment_time ASC'
        );
        $stmt->execute(['tenant_id' => $tenantId]);

        return $stmt->fetchAll();
    }

    /**
     * Get appointments for a PROVIDER or NURSE
     * (all appointments assigned to this provider).
     *
     * @param  int $tenantId
     * @param  int $providerId  users.id of the provider
     * @return array
     */
    public function findByProvider(int $tenantId, int $providerId): array {
        $stmt = $this->db->prepare(
            'SELECT * FROM appointments
             WHERE tenant_id   = :tenant_id
               AND provider_id  = :provider_id
               AND is_cancelled = 0
             ORDER BY appointment_date ASC, appointment_time ASC'
        );
        $stmt->execute([
            'tenant_id'   => $tenantId,
            'provider_id' => $providerId,
        ]);

        return $stmt->fetchAll();
    }

    /**
     * Get appointments for a PATIENT
     * (only appointments belonging to this patient).
     *
     * @param  int $tenantId
     * @param  int $patientId  patients.id of the logged-in patient
     * @return array
     */
    public function findByPatient(int $tenantId, int $patientId): array {
        $stmt = $this->db->prepare(
            'SELECT * FROM appointments
             WHERE tenant_id   = :tenant_id
               AND patient_id   = :patient_id
               AND is_cancelled = 0
             ORDER BY appointment_date ASC, appointment_time ASC'
        );
        $stmt->execute([
            'tenant_id'  => $tenantId,
            'patient_id' => $patientId,
        ]);

        return $stmt->fetchAll();
    }

    /**
     * Find a single appointment by ID, scoped to the tenant.
     *
     * @param  int $id
     * @param  int $tenantId
     * @return array|null
     */
    public function findById(int $id, int $tenantId): ?array {
        $stmt = $this->db->prepare(
            'SELECT * FROM appointments
             WHERE id        = :id
               AND tenant_id = :tenant_id
             LIMIT 1'
        );
        $stmt->execute(['id' => $id, 'tenant_id' => $tenantId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    /**
     * Check if a provider already has a booking at the same date + time.
     * Used before insert to prevent double-booking
     * (the DB also has a UNIQUE KEY uk_provider_slot as a safety net).
     *
     * @param  int    $providerId
     * @param  string $date  YYYY-MM-DD
     * @param  string $time  HH:MM:SS
     * @param  int|null $excludeId  Appointment ID to exclude (for updates)
     * @return bool  True if the slot is already taken
     */
    public function slotExists(int $providerId, string $date, string $time, ?int $excludeId = null): bool {
        if ($excludeId !== null) {
            $stmt = $this->db->prepare(
                'SELECT COUNT(*) FROM appointments
                 WHERE provider_id       = :provider_id
                   AND appointment_date  = :date
                   AND appointment_time  = :time
                   AND is_cancelled      = 0
                   AND id               != :exclude_id'
            );
            $stmt->execute([
                'provider_id' => $providerId,
                'date'        => $date,
                'time'        => $time,
                'exclude_id'  => $excludeId,
            ]);
        } else {
            $stmt = $this->db->prepare(
                'SELECT COUNT(*) FROM appointments
                 WHERE provider_id      = :provider_id
                   AND appointment_date = :date
                   AND appointment_time = :time
                   AND is_cancelled     = 0'
            );
            $stmt->execute([
                'provider_id' => $providerId,
                'date'        => $date,
                'time'        => $time,
            ]);
        }

        return (int) $stmt->fetchColumn() > 0;
    }

    // ----------------------------------------------------------------
    // WRITE
    // ----------------------------------------------------------------

    public function create(array $data, int $tenantId): int {
        $stmt = $this->db->prepare(
            'INSERT INTO appointments
             (tenant_id, patient_id, provider_id, appointment_date, appointment_time,
              notes, status, is_cancelled, created_at, updated_at)
             VALUES
             (:tenant_id, :patient_id, :provider_id, :appointment_date, :appointment_time,
              :notes, "scheduled", 0, NOW(), NOW())'
        );

        $stmt->execute([
            'tenant_id'        => $tenantId,
            'patient_id'       => (int) $data['patient_id'],
            'provider_id'      => (int) $data['provider_id'],
            'appointment_date' => $data['appointment_date'],
            'appointment_time' => $data['appointment_time'],
            'notes'            => $data['notes'] ?? null,
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Update allowed fields on an appointment.
     *
     * @param  int   $id
     * @param  int   $tenantId
     * @param  array $data   Subset of: appointment_date, appointment_time, notes, status
     * @return bool  true if a row was updated
     */
    public function update(int $id, int $tenantId, array $data): bool {
        $allowed    = ['appointment_date', 'appointment_time', 'notes', 'status'];
        $setClauses = [];
        $params     = ['id' => $id, 'tenant_id' => $tenantId];

        foreach ($data as $field => $value) {
            if (in_array($field, $allowed, true)) {
                $setClauses[]   = "$field = :$field";
                $params[$field] = $value;
            }
        }

        if (empty($setClauses)) {
            return false;
        }

        $setClauses[] = 'updated_at = NOW()';
        $sql = 'UPDATE appointments SET ' . implode(', ', $setClauses) .
               ' WHERE id = :id AND tenant_id = :tenant_id AND is_cancelled = 0';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount() > 0;
    }

    /**
     * Cancel an appointment (sets is_cancelled = 1, status = "cancelled").
     */
    public function cancel(int $id, int $tenantId): bool {
        $stmt = $this->db->prepare(
            'UPDATE appointments
             SET is_cancelled = 1,
                 status       = "cancelled",
                 updated_at   = NOW()
             WHERE id         = :id
               AND tenant_id  = :tenant_id
               AND is_cancelled = 0'
        );
        $stmt->execute(['id' => $id, 'tenant_id' => $tenantId]);

        return $stmt->rowCount() > 0;
    }
}
<?php

namespace App\Repositories;

use App\Config\Database;
<<<<<<< Updated upstream

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

=======
use PDO;

class AppointmentRepository {
>>>>>>> Stashed changes
    private $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

<<<<<<< Updated upstream
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
        $sql = 'SELECT COUNT(*) FROM appointments
                WHERE provider_id       = :provider_id
                  AND appointment_date  = :date
                  AND appointment_time  = :time
                  AND is_cancelled      = 0';

        $params = [
            'provider_id' => $providerId,
            'date'        => $date,
            'time'        => $time,
        ];

        // When updating, ignore the current appointment's own slot
        if ($excludeId !== null) {
            $sql .= ' AND id != :exclude_id';
            $params['exclude_id'] = $excludeId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn() > 0;
    }

    // ----------------------------------------------------------------
    // WRITE
    // ----------------------------------------------------------------

    /**
     * Create a new appointment.
     *
     * @param  array $data  Validated fields from the Service
     * @param  int   $tenantId
     * @return int   New appointment ID
     */
    public function create(array $data, int $tenantId): int {
        $stmt = $this->db->prepare(
            'INSERT INTO appointments
                (tenant_id, patient_id, provider_id,
                 appointment_date, appointment_time,
                 status, notes)
             VALUES
                (:tenant_id, :patient_id, :provider_id,
                 :appointment_date, :appointment_time,
                 :status, :notes)'
        );

        $stmt->execute([
            'tenant_id'        => $tenantId,
            'patient_id'       => $data['patient_id'],
            'provider_id'      => $data['provider_id'],
            'appointment_date' => $data['appointment_date'],
            'appointment_time' => $data['appointment_time'],
            'status'           => $data['status']  ?? 'scheduled',
            'notes'            => $data['notes']   ?? null,
=======
    /**
     * Insert a new appointment.
     */
    public function create(array $data, int $tenantId): int {
        $sql = "INSERT INTO appointments
                    (tenant_id, patient_id, provider_id, appointment_date, appointment_time, notes)
                VALUES
                    (:tenant_id, :patient_id, :provider_id, :appointment_date, :appointment_time, :notes)";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':tenant_id'        => $tenantId,
            ':patient_id'       => $data['patient_id'],
            ':provider_id'      => $data['provider_id'],
            ':appointment_date' => $data['appointment_date'],
            ':appointment_time' => $data['appointment_time'],
            ':notes'            => $data['notes'] ?? null,
>>>>>>> Stashed changes
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
<<<<<<< Updated upstream
     * Update an existing appointment.
     * Only the fields present in $data are updated (PATCH-style).
     *
     * @param  int   $id
     * @param  int   $tenantId
     * @param  array $data
     * @return bool
     */
    public function update(int $id, int $tenantId, array $data): bool {
        $allowed = [
            'patient_id', 'provider_id',
            'appointment_date', 'appointment_time',
            'status', 'notes'
        ];

        $setClauses = [];
        $params     = ['id' => $id, 'tenant_id' => $tenantId];
=======
     * Fetch a single appointment scoped to the tenant.
     */
    public function findById(int $id, int $tenantId): ?array {
        $sql = "SELECT * FROM appointments
                WHERE id = :id AND tenant_id = :tenant_id
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id, ':tenant_id' => $tenantId]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Fetch all appointments for a tenant, ordered by date/time.
     */
    public function findAll(int $tenantId): array {
        $sql = "SELECT * FROM appointments
                WHERE tenant_id = :tenant_id
                ORDER BY appointment_date ASC, appointment_time ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':tenant_id' => $tenantId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Update allowed fields on an appointment.
     */
    public function update(int $id, array $data, int $tenantId): bool {
        $allowed = ['appointment_date', 'appointment_time', 'provider_id', 'notes', 'status'];

        $setClauses = [];
        $params = [':id' => $id, ':tenant_id' => $tenantId];
>>>>>>> Stashed changes

        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $setClauses[] = "$field = :$field";
<<<<<<< Updated upstream
                $params[$field] = $data[$field];
=======
                $params[":$field"] = $data[$field];
>>>>>>> Stashed changes
            }
        }

        if (empty($setClauses)) {
<<<<<<< Updated upstream
            return false; // Nothing to update
        }

        $sql = 'UPDATE appointments
                SET ' . implode(', ', $setClauses) . '
                WHERE id        = :id
                  AND tenant_id = :tenant_id';
=======
            return false;
        }

        $sql = "UPDATE appointments
                SET " . implode(', ', $setClauses) . "
                WHERE id = :id AND tenant_id = :tenant_id AND is_cancelled = 0";
>>>>>>> Stashed changes

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount() > 0;
    }

    /**
<<<<<<< Updated upstream
     * Cancel an appointment (soft delete via is_cancelled + status).
     * Appointments are never hard-deleted — audit trail must be kept.
     *
     * @param  int $id
     * @param  int $tenantId
     * @return bool
     */
    public function cancel(int $id, int $tenantId): bool {
        $stmt = $this->db->prepare(
            'UPDATE appointments
             SET is_cancelled = 1,
                 status       = :status
             WHERE id        = :id
               AND tenant_id = :tenant_id
               AND is_cancelled = 0'
        );
        $stmt->execute([
            'status'    => 'cancelled',
            'id'        => $id,
            'tenant_id' => $tenantId,
        ]);

        return $stmt->rowCount() > 0;
    }
}
=======
     * Cancel an appointment: set status = 'cancelled' and is_cancelled = 1.
     */
    public function cancel(int $id, int $tenantId): bool {
        $sql = "UPDATE appointments
                SET status = 'cancelled', is_cancelled = 1
                WHERE id = :id AND tenant_id = :tenant_id AND is_cancelled = 0";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id, ':tenant_id' => $tenantId]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Check whether a provider already has a non-cancelled appointment at the given slot.
     * Pass $excludeId on updates so the existing record does not conflict with itself.
     */
    public function hasConflict(int $providerId, string $date, string $time, ?int $excludeId = null): bool {
        $sql = "SELECT id FROM appointments
                WHERE provider_id = :provider_id
                  AND appointment_date = :date
                  AND appointment_time = :time
                  AND is_cancelled = 0";

        $params = [
            ':provider_id' => $providerId,
            ':date'        => $date,
            ':time'        => $time,
        ];

        if ($excludeId !== null) {
            $sql .= " AND id != :exclude_id";
            $params[':exclude_id'] = $excludeId;
        }

        $sql .= " LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
>>>>>>> Stashed changes

<?php

namespace App\Repositories;

use App\Config\Database;

/**
 * AppointmentRepository
 *
 * Handles all SQL for the `appointments` table.
 *
 * Role-based filtering:
 *   - ADMIN          → findAll()         (tenant scope only)
 *   - PROVIDER/NURSE → findByProvider()  (appointments where provider_id = $providerId)
 *   - PATIENT        → findByPatient()   (appointments where patient_id  = $patientId)
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

    public function findAll(): array {
        $stmt = $this->db->prepare(
            'SELECT * FROM appointments
             WHERE is_cancelled = 0
             ORDER BY appointment_date ASC, appointment_time ASC'
        );
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function findByProvider(int $providerId): array {
        $stmt = $this->db->prepare(
            'SELECT * FROM appointments
             WHERE provider_id  = :provider_id
               AND is_cancelled = 0
             ORDER BY appointment_date ASC, appointment_time ASC'
        );
        $stmt->execute([
            'provider_id' => $providerId,
        ]);

        return $stmt->fetchAll();
    }

    public function findByPatient(int $patientId): array {
        $stmt = $this->db->prepare(
            'SELECT * FROM appointments
             WHERE patient_id   = :patient_id
               AND is_cancelled = 0
             ORDER BY appointment_date ASC, appointment_time ASC'
        );
        $stmt->execute([
            'patient_id' => $patientId,
        ]);

        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array {
        $stmt = $this->db->prepare(
            'SELECT * FROM appointments
             WHERE id        = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    /**
     * Check if a provider already has a booking at the same date + time.
     * Pass $excludeId when updating so the current appointment is not counted.
     *
     * @return bool  true if the slot is already taken
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

    public function create(array $data): int {
        $stmt = $this->db->prepare(
            'INSERT INTO appointments
             (patient_id, provider_id, appointment_date, appointment_time,
              notes, status, is_cancelled, created_at, updated_at)
             VALUES
             (:patient_id, :provider_id, :appointment_date, :appointment_time,
              :notes, "scheduled", 0, NOW(), NOW())'
        );

        $stmt->execute([
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
     * @param  array $data   Subset of: appointment_date, appointment_time, reason, status
     * @return bool  true if a row was updated
     */
    public function update(int $id, array $data): bool {
        $allowed    = ['appointment_date', 'appointment_time', 'notes', 'status'];
        $setClauses = [];
        $params     = ['id' => $id];

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
               ' WHERE id = :id AND is_cancelled = 0';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount() > 0;
    }

    /**
     * Cancel an appointment (sets is_cancelled = 1, status = "cancelled").
     */
    public function cancel(int $id): bool {
        $stmt = $this->db->prepare(
            'UPDATE appointments
             SET is_cancelled = 1,
                 status       = "cancelled",
                 updated_at   = NOW()
             WHERE id         = :id
               AND is_cancelled = 0'
        );
        $stmt->execute(['id' => $id]);

        return $stmt->rowCount() > 0;
    }
}

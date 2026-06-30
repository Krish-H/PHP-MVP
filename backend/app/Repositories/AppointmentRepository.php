<?php

namespace App\Repositories;

use App\Config\Database;
use App\Config\Env;
use App\Security\AES;

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
    private $key;

    public function __construct() {
        $this->db = Database::getConnection();
        $this->key = Env::get('AES_KEY');
    }

    private function decryptPatientName($row) {
        if (!empty($row['encrypted_patient_name'])) {
            $decrypted = AES::decrypt($row['encrypted_patient_name'], $this->key);
            $row['patient_name'] = $decrypted !== false ? $decrypted : null;
        } else {
            $row['patient_name'] = null;
        }
        unset($row['encrypted_patient_name']);
        return $row;
    }

    // ----------------------------------------------------------------
    // READ
    // ----------------------------------------------------------------

    public function findAll(): array {
        $stmt = $this->db->prepare(
            'SELECT a.*, p.name AS encrypted_patient_name, u.name AS provider_name 
             FROM appointments a
             LEFT JOIN patients p ON a.patient_id = p.id
             LEFT JOIN users u ON a.provider_id = u.id
             WHERE a.is_cancelled = 0
             ORDER BY a.appointment_date ASC, a.appointment_time ASC'
        );
        $stmt->execute();

        $rows = $stmt->fetchAll();
        return array_map([$this, 'decryptPatientName'], $rows);
    }

    public function findByProvider(int $providerId): array {
        $stmt = $this->db->prepare(
            'SELECT a.*, p.name AS encrypted_patient_name, u.name AS provider_name 
             FROM appointments a
             LEFT JOIN patients p ON a.patient_id = p.id
             LEFT JOIN users u ON a.provider_id = u.id
             WHERE a.provider_id  = :provider_id
               AND a.is_cancelled = 0
             ORDER BY a.appointment_date ASC, a.appointment_time ASC'
        );
        $stmt->execute([
            'provider_id' => $providerId,
        ]);

        $rows = $stmt->fetchAll();
        return array_map([$this, 'decryptPatientName'], $rows);
    }

    public function findByPatient(int $patientId): array {
        $stmt = $this->db->prepare(
            'SELECT a.*, p.name AS encrypted_patient_name, u.name AS provider_name 
             FROM appointments a
             LEFT JOIN patients p ON a.patient_id = p.id
             LEFT JOIN users u ON a.provider_id = u.id
             WHERE a.patient_id   = :patient_id
               AND a.is_cancelled = 0
             ORDER BY a.appointment_date ASC, a.appointment_time ASC'
        );
        $stmt->execute([
            'patient_id' => $patientId,
        ]);

        $rows = $stmt->fetchAll();
        return array_map([$this, 'decryptPatientName'], $rows);
    }

    public function findById(int $id): ?array {
        $stmt = $this->db->prepare(
            'SELECT a.*, p.name AS encrypted_patient_name, u.name AS provider_name 
             FROM appointments a
             LEFT JOIN patients p ON a.patient_id = p.id
             LEFT JOIN users u ON a.provider_id = u.id
             WHERE a.id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ? $this->decryptPatientName($row) : null;
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

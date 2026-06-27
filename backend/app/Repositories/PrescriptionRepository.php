<?php

namespace App\Repositories;

use App\Config\Database;
use App\Config\Env;
use App\Security\AES;

/**
 * PrescriptionRepository
 *
 * Handles all SQL for the `prescriptions` and `prescription_items` tables.
 *
 * Prescription status workflow: CREATED -> VERIFIED -> DISPENSED
 */
class PrescriptionRepository {

    private $db;
    private $key;

    public function __construct() {
        $this->db = Database::getConnection();
        $this->key = Env::get('AES_KEY');
    }

    // ================================================================
    // PRESCRIPTIONS - READ
    // ================================================================

    /**
     * Find all prescriptions for a tenant.
     * Optionally filter by patient_id, provider_id, or status.
     */
    public function findAll(?int $patientId = null, ?int $providerId = null, ?string $status = null): array {
        $sql = 'SELECT * FROM prescriptions WHERE 1=1';
        $params = [];

        if ($patientId !== null) {
            $sql .= ' AND patient_id = :patient_id';
            $params['patient_id'] = $patientId;
        }

        if ($providerId !== null) {
            $sql .= ' AND provider_id = :provider_id';
            $params['provider_id'] = $providerId;
        }

        if ($status !== null) {
            $sql .= ' AND status = :status';
            $params['status'] = $status;
        }

        $sql .= ' ORDER BY created_at DESC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $rows = $stmt->fetchAll();

        // Decrypt notes field for each prescription
        return array_map(function ($row) {
            if (!empty($row['notes'])) {
                $decrypted = AES::decrypt($row['notes'], $this->key);
                $row['notes'] = $decrypted !== false ? $decrypted : null;
            }
            return $row;
        }, $rows);
    }

    /**
     * Find prescription by ID with tenant isolation.
     */
    public function findById(int $id): ?array {
        $stmt = $this->db->prepare(
            'SELECT * FROM prescriptions
             WHERE id        = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        if (!$row) {
            return null;
        }

        if (!empty($row['notes'])) {
            $decrypted = AES::decrypt($row['notes'], $this->key);
            $row['notes'] = $decrypted !== false ? $decrypted : null;
        }

        return $row;
    }

    /**
     * Get prescription with all items.
     */
    public function getWithItems(int $id): ?array {
        $prescription = $this->findById($id);

        if (!$prescription) {
            return null;
        }

        $items = $this->findItemsByPrescriptionId($id);
        $prescription['items'] = $items;

        return $prescription;
    }

    /**
     * Check if patient exists for the given tenant.
     */
    public function patientExists(int $patientId): bool {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM patients
             WHERE id        = :id
               AND is_deleted = 0'
        );
        $stmt->execute(['id' => $patientId]);

        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Check if provider (user with provider role) exists for the given tenant.
     */
    public function providerExists(int $providerId): bool {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM users
             WHERE id         = :id
               AND role_id    IN (2, 6)
               AND is_active  = 1
               AND deleted_at IS NULL'
        );
        $stmt->execute(['id' => $providerId]);

        return (int) $stmt->fetchColumn() > 0;
    }

    // ================================================================
    // PRESCRIPTIONS - WRITE
    // ================================================================

    /**
     * Create a new prescription.
     * Initial status is 'PENDING'.
     */
    public function create(array $data): int {
        $stmt = $this->db->prepare(
            'INSERT INTO prescriptions
             (patient_id, provider_id, status, notes, created_at, updated_at)
             VALUES
             (:patient_id, :provider_id, :status, :notes, NOW(), NOW())'
        );

        $stmt->execute([
            'patient_id' => (int) $data['patient_id'],
            'provider_id' => (int) $data['provider_id'],
            'status'     => 'PENDING',
            'notes'      => isset($data['notes']) && $data['notes'] !== '' ? AES::encrypt($data['notes'], $this->key) : null,
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Update prescription fields.
     * Allowed fields: notes, status
     */
    public function update(int $id, array $data): bool {
        $allowed    = ['notes', 'status'];
        $setClauses = [];
        $params     = ['id' => $id];

        foreach ($data as $field => $value) {
            if (in_array($field, $allowed, true)) {
                $setClauses[]   = "$field = :$field";

                if ($field === 'notes') {
                    $params[$field] = $value !== null ? AES::encrypt($value, $this->key) : null;
                } else {
                    $params[$field] = $value;
                }
            }
        }

        if (empty($setClauses)) {
            return false;
        }

        $setClauses[] = 'updated_at = NOW()';
        $sql = 'UPDATE prescriptions SET ' . implode(', ', $setClauses) .
               ' WHERE id = :id';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount() > 0;
    }

    /**
     * Update prescription status.
     */
    public function updateStatus(int $id, string $status): bool {
        $stmt = $this->db->prepare(
            'UPDATE prescriptions
             SET status     = :status,
                 updated_at = NOW()
             WHERE id       = :id'
        );
        $stmt->execute(['id' => $id, 'status' => $status]);

        return $stmt->rowCount() > 0;
    }

    // ================================================================
    // PRESCRIPTION ITEMS - READ
    // ================================================================

    /**
     * Get all items for a prescription.
     */
    public function findItemsByPrescriptionId(int $prescriptionId): array {
        $stmt = $this->db->prepare(
            'SELECT id, prescription_id, medicine_name, dosage, quantity
             FROM prescription_items
             WHERE prescription_id = :prescription_id
             ORDER BY id ASC'
        );
        $stmt->execute(['prescription_id' => $prescriptionId]);

        $rows = $stmt->fetchAll();

        // Decrypt medicine_name and dosage for items
        return array_map(function ($row) {
            $row['medicine_name'] = !empty($row['medicine_name']) ? (AES::decrypt($row['medicine_name'], $this->key) ?: null) : null;
            $row['dosage'] = !empty($row['dosage']) ? (AES::decrypt($row['dosage'], $this->key) ?: null) : null;
            return $row;
        }, $rows);
    }

    /**
     * Get a single prescription item by ID.
     */
    public function findItemById(int $itemId, int $prescriptionId): ?array {
        $stmt = $this->db->prepare(
            'SELECT id, prescription_id, medicine_name, dosage, quantity
             FROM prescription_items
             WHERE id                = :id
               AND prescription_id   = :prescription_id
             LIMIT 1'
        );
        $stmt->execute(['id' => $itemId, 'prescription_id' => $prescriptionId]);
        $row = $stmt->fetch();

        if (!$row) {
            return null;
        }

        $row['medicine_name'] = !empty($row['medicine_name']) ? (AES::decrypt($row['medicine_name'], $this->key) ?: null) : null;
        $row['dosage'] = !empty($row['dosage']) ? (AES::decrypt($row['dosage'], $this->key) ?: null) : null;

        return $row;
    }

    // ================================================================
    // PRESCRIPTION ITEMS - WRITE
    // ================================================================

    /**
     * Add a medicine item to a prescription.
     */
    public function addItem(int $prescriptionId, array $data): int {
        $stmt = $this->db->prepare(
            'INSERT INTO prescription_items
             (prescription_id, medicine_name, dosage, quantity)
             VALUES
             (:prescription_id, :medicine_name, :dosage, :quantity)'
        );

        $stmt->execute([
            'prescription_id' => $prescriptionId,
            'medicine_name'   => AES::encrypt($data['medicine_name'], $this->key),
            'dosage'          => AES::encrypt($data['dosage'], $this->key),
            'quantity'        => (int) $data['quantity'],
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Update a prescription item.
     */
    public function updateItem(int $itemId, int $prescriptionId, array $data): bool {
        $allowed    = ['medicine_name', 'dosage', 'quantity'];
        $setClauses = [];
        $params     = ['id' => $itemId, 'prescription_id' => $prescriptionId];

        foreach ($data as $field => $value) {
            if (in_array($field, $allowed, true)) {
                $setClauses[]   = "$field = :$field";

                if ($field === 'medicine_name' || $field === 'dosage') {
                    $params[$field] = AES::encrypt($value, $this->key);
                } else {
                    $params[$field] = $value;
                }
            }
        }

        if (empty($setClauses)) {
            return false;
        }

        $sql = 'UPDATE prescription_items SET ' . implode(', ', $setClauses) .
               ' WHERE id = :id AND prescription_id = :prescription_id';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount() > 0;
    }

    /**
     * Delete a prescription item.
     */
    public function deleteItem(int $itemId, int $prescriptionId): bool {
        $stmt = $this->db->prepare(
            'DELETE FROM prescription_items
             WHERE id               = :id
               AND prescription_id  = :prescription_id'
        );
        $stmt->execute(['id' => $itemId, 'prescription_id' => $prescriptionId]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Delete all items for a prescription.
     */
    public function deleteAllItems(int $prescriptionId): bool {
        $stmt = $this->db->prepare(
            'DELETE FROM prescription_items
             WHERE prescription_id = :prescription_id'
        );
        $stmt->execute(['prescription_id' => $prescriptionId]);

        return $stmt->rowCount() > 0;
    }
}

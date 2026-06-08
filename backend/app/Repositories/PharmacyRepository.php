<?php

namespace App\Repositories;

use App\Config\Database;
use App\Config\Env;
use App\Security\AES;

/**
 * PharmacyRepository
 *
 * Specialized repository for pharmacy operations.
 * Handles pharmacy-specific queries for dispensing, verification, and inventory tracking.
 */
class PharmacyRepository {

    private $db;

    private $key;

    public function __construct() {
        $this->db = Database::getConnection();
        $this->key = Env::get('AES_KEY');
    }

    /**
     * Find prescriptions by status for a tenant (pharmacy view).
     * Used to get prescriptions pending verification or dispensing.
     */
    public function findByStatus(int $tenantId, string $status): array {
        $stmt = $this->db->prepare(
            'SELECT * FROM prescriptions
             WHERE tenant_id = :tenant_id
               AND status    = :status
             ORDER BY created_at ASC'
        );
        $stmt->execute(['tenant_id' => $tenantId, 'status' => $status]);

        return $stmt->fetchAll();
    }

    /**
     * Get prescriptions pending verification (PENDING status).
     */
    public function getPendingVerification(int $tenantId): array {
        return $this->findByStatus($tenantId, 'PENDING');
    }

    /**
     * Get prescriptions pending dispensing (VERIFIED status).
     */
    public function getPendingDispensing(int $tenantId): array {
        return $this->findByStatus($tenantId, 'VERIFIED');
    }

    /**
     * Get dispensed prescriptions (DISPENSED status).
     */
    public function getDispensed(int $tenantId): array {
        return $this->findByStatus($tenantId, 'DISPENSED');
    }

    /**
     * Verify a prescription (change status from PENDING to VERIFIED).
     */
    public function verifyPrescription(int $prescriptionId, int $tenantId): bool {
        $stmt = $this->db->prepare(
            'UPDATE prescriptions
             SET status     = :status,
                 updated_at = NOW()
             WHERE id       = :id
               AND tenant_id = :tenant_id
               AND status   = "PENDING"'
        );
        $stmt->execute([
            'id'        => $prescriptionId,
            'tenant_id' => $tenantId,
            'status'    => 'VERIFIED',
        ]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Dispense a prescription (change status from VERIFIED to DISPENSED).
     */
    public function dispensePrescription(int $prescriptionId, int $tenantId): bool {
        $stmt = $this->db->prepare(
            'UPDATE prescriptions
             SET status     = :status,
                 updated_at = NOW()
             WHERE id       = :id
               AND tenant_id = :tenant_id
               AND status   = "VERIFIED"'
        );
        $stmt->execute([
            'id'        => $prescriptionId,
            'tenant_id' => $tenantId,
            'status'    => 'DISPENSED',
        ]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Get count of prescriptions by status for a tenant.
     * Useful for dashboard summaries.
     */
    public function getCountByStatus(int $tenantId, string $status): int {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM prescriptions
             WHERE tenant_id = :tenant_id
               AND status    = :status'
        );
        $stmt->execute(['tenant_id' => $tenantId, 'status' => $status]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Get pharmacy stats (counts for each status).
     */
    public function getPharmacyStats(int $tenantId): array {
        $pending = $this->getCountByStatus($tenantId, 'PENDING');
        $verified = $this->getCountByStatus($tenantId, 'VERIFIED');
        $dispensed = $this->getCountByStatus($tenantId, 'DISPENSED');

        return [
            'pending_verification' => $pending,
            'pending_dispensing'   => $verified,
            'dispensed'            => $dispensed,
            'total'                => $pending + $verified + $dispensed,
        ];
    }

    /**
     * Check if pharmacist exists for the given tenant.
     */
    public function pharmacistExists(int $pharmacistId, int $tenantId): bool {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM users
             WHERE id         = :id
               AND tenant_id  = :tenant_id
               AND role_id    = 5
               AND is_active  = 1
               AND deleted_at IS NULL'
        );
        $stmt->execute(['id' => $pharmacistId, 'tenant_id' => $tenantId]);

        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Get prescription with full details including items.
     */
    public function getPrescriptionWithItems(int $prescriptionId, int $tenantId): ?array {
        // Fetch prescription
        $stmt = $this->db->prepare(
            'SELECT * FROM prescriptions WHERE id = :id AND tenant_id = :tenant_id LIMIT 1'
        );
        $stmt->execute(['id' => $prescriptionId, 'tenant_id' => $tenantId]);
        $prescription = $stmt->fetch();

        if (!$prescription) {
            return null;
        }

        // Decrypt notes if present
        if (!empty($prescription['notes'])) {
            $decrypted = AES::decrypt($prescription['notes'], $this->key);
            $prescription['notes'] = $decrypted !== false ? $decrypted : null;
        }

        // Fetch and decrypt items
        $stmt = $this->db->prepare(
            'SELECT id, prescription_id, medicine_name, dosage, quantity FROM prescription_items WHERE prescription_id = :prescription_id ORDER BY id ASC'
        );
        $stmt->execute(['prescription_id' => $prescriptionId]);
        $items = $stmt->fetchAll();

        $items = array_map(function ($row) {
            $row['medicine_name'] = !empty($row['medicine_name']) ? (AES::decrypt($row['medicine_name'], $this->key) ?: null) : null;
            $row['dosage'] = !empty($row['dosage']) ? (AES::decrypt($row['dosage'], $this->key) ?: null) : null;
            return $row;
        }, $items);

        $prescription['items'] = $items;

        return $prescription;
    }
}

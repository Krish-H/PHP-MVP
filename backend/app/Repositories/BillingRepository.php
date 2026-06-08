<?php

namespace App\Repositories;

use App\Config\Database;

class BillingRepository {
    private $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    public function create(array $data, int $tenantId): int {
        $stmt = $this->db->prepare('
            INSERT INTO invoices (tenant_id, patient_id, invoice_number, amount, status)
            VALUES (:tenant_id, :patient_id, :invoice_number, :amount, :status)
        ');
        $stmt->execute([
            'tenant_id'      => $tenantId,
            'patient_id'     => $data['patient_id'],
            'invoice_number' => $data['invoice_number'],
            'amount'         => $data['amount'],
            'status'         => $data['status']
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function findAll(int $tenantId): array {
        $stmt = $this->db->prepare('
            SELECT * FROM invoices
            WHERE tenant_id = :tenant_id
            ORDER BY created_at DESC
        ');
        $stmt->execute(['tenant_id' => $tenantId]);
        return $stmt->fetchAll();
    }

    public function findAllForPatient(int $tenantId, int $patientId): array {
        $stmt = $this->db->prepare('
            SELECT * FROM invoices
            WHERE tenant_id = :tenant_id AND patient_id = :patient_id
            ORDER BY created_at DESC
        ');
        $stmt->execute(['tenant_id' => $tenantId, 'patient_id' => $patientId]);
        return $stmt->fetchAll();
    }

    public function findByPatientId(int $patientId, int $tenantId): array {
        return $this->findAllForPatient($tenantId, $patientId);
    }

    public function findById(int $id, int $tenantId) {
        $stmt = $this->db->prepare('
            SELECT * FROM invoices
            WHERE id = :id AND tenant_id = :tenant_id
            LIMIT 1
        ');
        $stmt->execute(['id' => $id, 'tenant_id' => $tenantId]);
        return $stmt->fetch();
    }

    public function updateStatus(int $id, string $status, int $tenantId): bool {
        $stmt = $this->db->prepare('
            UPDATE invoices
            SET status = :status
            WHERE id = :id AND tenant_id = :tenant_id
        ');
        $stmt->execute([
            'status'    => $status,
            'id'        => $id,
            'tenant_id' => $tenantId
        ]);
        return $stmt->rowCount() > 0;
    }

    public function createPayment(int $invoiceId, float $amount, string $status): int {
        $stmt = $this->db->prepare('
            INSERT INTO payments (invoice_id, payment_amount, payment_status)
            VALUES (:invoice_id, :payment_amount, :payment_status)
        ');
        $stmt->execute([
            'invoice_id'     => $invoiceId,
            'payment_amount' => $amount,
            'payment_status' => $status
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function getPendingSummary(int $tenantId): array {
        $stmt = $this->db->prepare('
            SELECT COUNT(*) as pending_count, COALESCE(SUM(amount), 0) as pending_amount
            FROM invoices
            WHERE tenant_id = :tenant_id AND status = "pending"
        ');
        $stmt->execute(['tenant_id' => $tenantId]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    public function getPaidSummary(int $tenantId): array {
        $stmt = $this->db->prepare('
            SELECT COUNT(*) as paid_count, COALESCE(SUM(amount), 0) as paid_amount
            FROM invoices
            WHERE tenant_id = :tenant_id AND status = "paid"
        ');
        $stmt->execute(['tenant_id' => $tenantId]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }
}

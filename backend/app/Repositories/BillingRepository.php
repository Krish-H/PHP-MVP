<?php

namespace App\Repositories;

use App\Config\Database;

class BillingRepository {
    private $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    public function create(array $data): int {
        $stmt = $this->db->prepare('
            INSERT INTO invoices (patient_id, invoice_number, amount, status)
            VALUES (:patient_id, :invoice_number, :amount, :status)
        ');
        $stmt->execute([
            'patient_id'     => $data['patient_id'],
            'invoice_number' => $data['invoice_number'],
            'amount'         => $data['amount'],
            'status'         => $data['status']
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function findAll(): array {
        $stmt = $this->db->prepare('
            SELECT id, patient_id, invoice_number, amount, status, created_at, updated_at FROM invoices
            ORDER BY created_at DESC
        ');
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function findAllForPatient(int $patientId): array {
        $stmt = $this->db->prepare('
            SELECT id, patient_id, invoice_number, amount, status, created_at, updated_at FROM invoices
            WHERE patient_id = :patient_id
            ORDER BY created_at DESC
        ');
        $stmt->execute(['patient_id' => $patientId]);
        return $stmt->fetchAll();
    }

    public function findByPatientId(int $patientId): array {
        return $this->findAllForPatient($patientId);
    }

    public function findById(int $id) {
        $stmt = $this->db->prepare('
            SELECT id, patient_id, invoice_number, amount, status, created_at, updated_at FROM invoices
            WHERE id = :id
            LIMIT 1
        ');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

    public function updateStatus(int $id, string $status): bool {
        $stmt = $this->db->prepare('
            UPDATE invoices
            SET status = :status
            WHERE id = :id
        ');
        $stmt->execute([
            'status'    => $status,
            'id'        => $id
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

    public function getPendingSummary(): array {
        $stmt = $this->db->prepare('
            SELECT COUNT(*) as pending_count, COALESCE(SUM(amount), 0) as pending_amount
            FROM invoices
            WHERE status = "pending"
        ');
        $stmt->execute();
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    public function getPaidSummary(): array {
        $stmt = $this->db->prepare('
            SELECT COUNT(*) as paid_count, COALESCE(SUM(amount), 0) as paid_amount
            FROM invoices
            WHERE status = "paid"
        ');
        $stmt->execute();
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }
}

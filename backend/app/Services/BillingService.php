<?php

namespace App\Services;

use App\Repositories\BillingRepository;
use App\Repositories\PatientRepository;
use App\Helpers\Validator;
use Exception;

class BillingService {
    private $billingRepo;
    private $patientRepo;

    public function __construct() {
        $this->billingRepo = new BillingRepository();
        $this->patientRepo = new PatientRepository();
    }

    public function listInvoices(): array {
        return $this->billingRepo->findAll();
    }

    public function getMyInvoices(int $userId): array {
        $patient = $this->patientRepo->findByPatientUserId($userId);
        if (!$patient) {
            throw new Exception('Patient mapping not found', 404);
        }
        return $this->billingRepo->findByPatientId($patient['id']);
    }

    public function createInvoice(array $data): int {
        if (!Validator::required($data, ['patient_id', 'invoice_number', 'amount'])) {
            throw new Exception('patient_id, invoice_number, and amount are required', 400);
        }

        $patient = $this->patientRepo->findById((int) $data['patient_id']);
        if (!$patient) {
            throw new Exception('Patient not found', 404);
        }

        $data['status'] = $data['status'] ?? 'pending';
        if (!in_array($data['status'], ['pending', 'paid'])) {
            throw new Exception('Invalid status', 400);
        }

        return $this->billingRepo->create($data);
    }

    public function updatePaymentStatus(int $id, array $data): void {
        if (!isset($data['status'])) {
            throw new Exception('Status is required', 400);
        }

        $status = $data['status'];
        if (!in_array($status, ['pending', 'paid'])) {
            throw new Exception('Invalid status', 400);
        }

        $invoice = $this->billingRepo->findById($id);
        if (!$invoice) {
            throw new Exception('Invoice not found', 404);
        }

        if ($invoice['status'] === $status) {
            throw new Exception("Invoice is already marked as $status", 400);
        }

        $this->billingRepo->updateStatus($id, $status);

        if ($status === 'paid') {
            $this->billingRepo->createPayment($id, (float) $invoice['amount'], 'completed');
        }
    }

    public function getPendingSummary(): array {
        $result = $this->billingRepo->getPendingSummary();
        return [
            'pending_count' => (int) $result['pending_count'],
            'pending_amount' => (float) $result['pending_amount']
        ];
    }

    public function getPaidSummary(): array {
        $result = $this->billingRepo->getPaidSummary();
        return [
            'paid_count' => (int) $result['paid_count'],
            'paid_amount' => (float) $result['paid_amount']
        ];
    }
}

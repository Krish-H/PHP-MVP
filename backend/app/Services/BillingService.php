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

    public function listInvoices(int $tenantId, int $roleId, int $userId): array {
        if ($roleId === \App\Config\Roles::PATIENT) {
            $patient = $this->patientRepo->findByPatientUserId($userId, $tenantId);
            if (!$patient) {
                return [];
            }
            return $this->billingRepo->findAllForPatient($tenantId, $patient['id']);
        }

        return $this->billingRepo->findAll($tenantId);
    }

    public function getMyInvoices(int $userId, int $tenantId): array {
        $patient = $this->patientRepo->findByPatientUserId($userId, $tenantId);
        if (!$patient) {
            throw new Exception('Patient mapping not found', 404);
        }
        return $this->billingRepo->findByPatientId($patient['id'], $tenantId);
    }

    public function createInvoice(array $data, int $tenantId): int {
        if (!Validator::required($data, ['patient_id', 'invoice_number', 'amount'])) {
            throw new Exception('patient_id, invoice_number, and amount are required', 400);
        }

        $patient = $this->patientRepo->findById((int) $data['patient_id'], $tenantId);
        if (!$patient) {
            throw new Exception('Patient not found', 404);
        }

        $data['status'] = $data['status'] ?? 'pending';
        if (!in_array($data['status'], ['pending', 'paid'])) {
            throw new Exception('Invalid status', 400);
        }

        return $this->billingRepo->create($data, $tenantId);
    }

    public function updatePaymentStatus(int $id, array $data, int $tenantId): void {
        if (!isset($data['status'])) {
            throw new Exception('Status is required', 400);
        }

        $status = $data['status'];
        if (!in_array($status, ['pending', 'paid'])) {
            throw new Exception('Invalid status', 400);
        }

        $invoice = $this->billingRepo->findById($id, $tenantId);
        if (!$invoice) {
            throw new Exception('Invoice not found', 404);
        }

        if ($invoice['status'] === $status) {
            throw new Exception("Invoice is already marked as $status", 400);
        }

        $this->billingRepo->updateStatus($id, $status, $tenantId);

        if ($status === 'paid') {
            $this->billingRepo->createPayment($id, (float) $invoice['amount'], 'completed');
        }
    }

    public function getPendingSummary(int $tenantId): array {
        $result = $this->billingRepo->getPendingSummary($tenantId);
        return [
            'pending_count' => (int) $result['pending_count'],
            'pending_amount' => (float) $result['pending_amount']
        ];
    }

    public function getPaidSummary(int $tenantId): array {
        $result = $this->billingRepo->getPaidSummary($tenantId);
        return [
            'paid_count' => (int) $result['paid_count'],
            'paid_amount' => (float) $result['paid_amount']
        ];
    }
}

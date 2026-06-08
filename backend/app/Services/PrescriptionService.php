<?php

namespace App\Services;

use App\Repositories\PrescriptionRepository;
use Exception;

/**
 * PrescriptionService
 *
 * Handles business logic for prescriptions.
 * Validates all data and orchestrates repository operations.
 *
 * Status workflow: CREATED -> VERIFIED -> DISPENSED
 */
class PrescriptionService {

    private $prescriptionRepo;

    /** Valid status transitions: current status => allowed next statuses */
    private const STATUS_TRANSITIONS = [
        'PENDING'   => ['VERIFIED', 'CANCELLED'],
        'VERIFIED'  => ['DISPENSED', 'CANCELLED'],
        'DISPENSED' => [],
        'CANCELLED' => [],
    ];

    public function __construct() {
        $this->prescriptionRepo = new PrescriptionRepository();
    }

    // ================================================================
    // PUBLIC API
    // ================================================================

    /**
     * Create a new prescription with items.
     *
     * @param array $data   Must include: patient_id, provider_id, items (array)
     *                      Optional: notes
     * @param int $tenantId
     * @return int          Prescription ID
     */
    public function createPrescription(array $data, int $tenantId): int {
        $this->validateRequired($data, ['patient_id', 'provider_id', 'items']);
        $this->validatePatientExists($data['patient_id'], $tenantId);
        $this->validateProviderExists($data['provider_id'], $tenantId);
        $this->validateItems($data['items']);

        $prescriptionId = $this->prescriptionRepo->create($data, $tenantId);

        // Add items
        foreach ($data['items'] as $item) {
            $this->prescriptionRepo->addItem($prescriptionId, $item);
        }

        return $prescriptionId;
    }

    /**
     * Get a single prescription with all items.
     */
    public function getPrescription(int $id, int $tenantId): array {
        $prescription = $this->prescriptionRepo->getWithItems($id, $tenantId);

        if (!$prescription) {
            throw new Exception('Prescription not found', 404);
        }

        return $prescription;
    }

    /**
     * List prescriptions for a tenant.
     * Optionally filter by patient_id, provider_id, or status.
     */
    public function listPrescriptions(int $tenantId, ?int $patientId = null, ?int $providerId = null, ?string $status = null): array {
        // Validate filters if provided
        if ($patientId !== null) {
            $this->validatePatientExists($patientId, $tenantId);
        }

        if ($providerId !== null) {
            $this->validateProviderExists($providerId, $tenantId);
        }

        if ($status !== null) {
            $this->validateStatus($status);
        }

        return $this->prescriptionRepo->findAll($tenantId, $patientId, $providerId, $status);
    }

    /**
     * Update prescription (notes only for providers).
     */
    public function updatePrescription(int $id, array $data, int $tenantId): void {
        $existing = $this->prescriptionRepo->findById($id, $tenantId);

        if (!$existing) {
            throw new Exception('Prescription not found', 404);
        }

        // Only allow updating notes for provider-initiated updates
        if (!isset($data['notes'])) {
            throw new Exception('No updates provided', 400);
        }

        $updated = $this->prescriptionRepo->update($id, $tenantId, ['notes' => $data['notes']]);

        if (!$updated) {
            throw new Exception('No changes were made', 400);
        }
    }

    /**
     * Verify a prescription (pharmacist action).
     * Changes status from CREATED -> VERIFIED.
     */
    public function verifyPrescription(int $id, int $tenantId): void {
        $prescription = $this->prescriptionRepo->findById($id, $tenantId);

        if (!$prescription) {
            throw new Exception('Prescription not found', 404);
        }

        if ($prescription['status'] !== 'PENDING') {
            throw new Exception(
                "Cannot verify prescription with status '{$prescription['status']}'. Must be PENDING.",
                400
            );
        }

        $updated = $this->prescriptionRepo->updateStatus($id, $tenantId, 'VERIFIED');

        if (!$updated) {
            throw new Exception('Failed to verify prescription', 500);
        }
    }

    /**
     * Dispense a prescription (pharmacist action).
     * Changes status from VERIFIED -> DISPENSED.
     */
    public function dispensePrescription(int $id, int $tenantId): void {
        $prescription = $this->prescriptionRepo->findById($id, $tenantId);

        if (!$prescription) {
            throw new Exception('Prescription not found', 404);
        }

        if ($prescription['status'] !== 'VERIFIED') {
            throw new Exception(
                "Cannot dispense prescription with status '{$prescription['status']}'. Must be VERIFIED.",
                400
            );
        }

        $updated = $this->prescriptionRepo->updateStatus($id, $tenantId, 'DISPENSED');

        if (!$updated) {
            throw new Exception('Failed to dispense prescription', 500);
        }
    }

    /**
     * Update prescription status directly (admin/special operations).
     */
    public function updatePrescriptionStatus(int $id, array $data, int $tenantId): void {
        $this->validateRequired($data, ['status']);
        $this->validateStatus($data['status']);

        $existing = $this->prescriptionRepo->findById($id, $tenantId);

        if (!$existing) {
            throw new Exception('Prescription not found', 404);
        }

        // Validate status transition
        $this->validateStatusTransition($existing['status'], $data['status']);

        $updated = $this->prescriptionRepo->updateStatus($id, $tenantId, $data['status']);

        if (!$updated) {
            throw new Exception('Failed to update prescription status', 500);
        }
    }

    /**
     * Add item to prescription.
     */
    public function addItem(int $prescriptionId, array $data, int $tenantId): int {
        $prescription = $this->prescriptionRepo->findById($prescriptionId, $tenantId);

        if (!$prescription) {
            throw new Exception('Prescription not found', 404);
        }

        // Can only add items to PENDING prescriptions
        if ($prescription['status'] !== 'PENDING') {
            throw new Exception('Can only add items to prescriptions with PENDING status', 400);
        }

        $this->validateRequired($data, ['medicine_name', 'dosage', 'quantity']);
        $this->validateMedicineName($data['medicine_name']);
        $this->validateDosage($data['dosage']);
        $this->validateQuantity($data['quantity']);

        return $this->prescriptionRepo->addItem($prescriptionId, $data);
    }

    /**
     * Update prescription item.
     */
    public function updateItem(int $prescriptionId, int $itemId, array $data, int $tenantId): void {
        $prescription = $this->prescriptionRepo->findById($prescriptionId, $tenantId);

        if (!$prescription) {
            throw new Exception('Prescription not found', 404);
        }

        $item = $this->prescriptionRepo->findItemById($itemId, $prescriptionId);

        if (!$item) {
            throw new Exception('Item not found', 404);
        }

        // Can only update items in PENDING prescriptions
        if ($prescription['status'] !== 'PENDING') {
            throw new Exception('Can only update items in prescriptions with PENDING status', 400);
        }

        // Validate fields if provided
        if (isset($data['medicine_name'])) {
            $this->validateMedicineName($data['medicine_name']);
        }

        if (isset($data['dosage'])) {
            $this->validateDosage($data['dosage']);
        }

        if (isset($data['quantity'])) {
            $this->validateQuantity($data['quantity']);
        }

        $updated = $this->prescriptionRepo->updateItem($itemId, $prescriptionId, $data);

        if (!$updated) {
            throw new Exception('No changes were made', 400);
        }
    }

    /**
     * Delete prescription item.
     */
    public function deleteItem(int $prescriptionId, int $itemId, int $tenantId): void {
        $prescription = $this->prescriptionRepo->findById($prescriptionId, $tenantId);

        if (!$prescription) {
            throw new Exception('Prescription not found', 404);
        }

        $item = $this->prescriptionRepo->findItemById($itemId, $prescriptionId);

        if (!$item) {
            throw new Exception('Item not found', 404);
        }

        // Can only delete items from PENDING prescriptions
        if ($prescription['status'] !== 'PENDING') {
            throw new Exception('Can only delete items from prescriptions with PENDING status', 400);
        }

        $deleted = $this->prescriptionRepo->deleteItem($itemId, $prescriptionId);

        if (!$deleted) {
            throw new Exception('Failed to delete item', 500);
        }
    }

    // ================================================================
    // PRIVATE VALIDATION HELPERS
    // ================================================================

    private function validateRequired(array $data, array $required): void {
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new Exception("Missing required field: $field", 422);
            }
        }
    }

    private function validatePatientExists(int $patientId, int $tenantId): void {
        if (!$this->prescriptionRepo->patientExists($patientId, $tenantId)) {
            throw new Exception('Patient not found', 404);
        }
    }

    private function validateProviderExists(int $providerId, int $tenantId): void {
        if (!$this->prescriptionRepo->providerExists($providerId, $tenantId)) {
            throw new Exception('Provider not found', 404);
        }
    }

    private function validateMedicineName(string $medicineName): void {
        if (trim($medicineName) === '') {
            throw new Exception('Medicine name cannot be empty', 422);
        }

        if (strlen($medicineName) > 255) {
            throw new Exception('Medicine name is too long (max 255 characters)', 422);
        }
    }

    private function validateDosage(string $dosage): void {
        if (trim($dosage) === '') {
            throw new Exception('Dosage cannot be empty', 422);
        }

        if (strlen($dosage) > 100) {
            throw new Exception('Dosage is too long (max 100 characters)', 422);
        }
    }

    private function validateQuantity($quantity): void {
        $qty = (int) $quantity;

        if ($qty <= 0) {
            throw new Exception('Quantity must be greater than 0', 422);
        }

        if ($qty > 999999) {
            throw new Exception('Quantity is too large', 422);
        }
    }

    private function validateStatus(string $status): void {
        $validStatuses = array_keys(self::STATUS_TRANSITIONS);

        if (!in_array($status, $validStatuses, true)) {
            throw new Exception(
                "Invalid status '$status'. Allowed: " . implode(', ', $validStatuses),
                422
            );
        }
    }

    private function validateItems(array $items): void {
        if (empty($items)) {
            throw new Exception('At least one item is required', 422);
        }

        foreach ($items as $index => $item) {
            if (empty($item['medicine_name'])) {
                throw new Exception("Item $index: medicine_name is required", 422);
            }

            if (empty($item['dosage'])) {
                throw new Exception("Item $index: dosage is required", 422);
            }

            if (empty($item['quantity'])) {
                throw new Exception("Item $index: quantity is required", 422);
            }

            $this->validateMedicineName($item['medicine_name']);
            $this->validateDosage($item['dosage']);
            $this->validateQuantity($item['quantity']);
        }
    }

    private function validateStatusTransition(string $current, string $next): void {
        $allowed = self::STATUS_TRANSITIONS[$current] ?? [];

        if (!in_array($next, $allowed, true)) {
            throw new Exception(
                "Cannot transition prescription from '$current' to '$next'",
                400
            );
        }
    }
}

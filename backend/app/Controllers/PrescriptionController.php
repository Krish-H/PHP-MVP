<?php

namespace App\Controllers;

use App\Helpers\Request;
use App\Helpers\Response;
use App\Services\PrescriptionService;
use Exception;

/**
 * PrescriptionController
 *
 * Handles prescription management APIs.
 * - Providers create/update prescriptions
 * - Pharmacists verify and dispense
 * - All endpoints enforce tenant isolation
 */
class PrescriptionController {

    private $prescriptionService;

    public function __construct() {
        $this->prescriptionService = new PrescriptionService();
    }

    // ================================================================
    // PRESCRIPTION LISTING AND RETRIEVAL
    // ================================================================

    /**
     * GET /prescriptions
     * List all prescriptions (with optional filters).
     *
     * Query params:
     *   - patient_id (optional)
     *   - provider_id (optional)
     *   - status (optional): PENDING, VERIFIED, DISPENSED, CANCELLED
     */
    public function index() {
        try {
            $patientId = isset($_GET['patient_id']) ? (int) $_GET['patient_id'] : null;
            $providerId = isset($_GET['provider_id']) ? (int) $_GET['provider_id'] : null;
            $status = isset($_GET['status']) ? $_GET['status'] : null;

            $prescriptions = $this->prescriptionService->listPrescriptions(
                $patientId,
                $providerId,
                $status
            );

            Response::json(['prescriptions' => $prescriptions], 200);
        } catch (Exception $e) {
            Response::json(['error' => $e->getMessage()], $e->getCode() ?: 500);
        }
    }

    /**
     * GET /prescriptions/{id}
     * Get a single prescription with all items.
     */
    public function show($params) {
        try {
            $prescription = $this->prescriptionService->getPrescription(
                $params['id']
            );

            Response::json(['prescription' => $prescription], 200);
        } catch (Exception $e) {
            Response::json(['error' => $e->getMessage()], $e->getCode() ?: 500);
        }
    }

    // ================================================================
    // PRESCRIPTION CREATION AND UPDATES (PROVIDER APIs)
    // ================================================================

    /**
     * POST /prescriptions
     * Create a new prescription with items.
     *
     * Request body:
     * {
     *   "patient_id": 123,
     *   "provider_id": 456,
     *   "notes": "optional notes",
     *   "items": [
     *     {
     *       "medicine_name": "Aspirin",
     *       "dosage": "500mg",
     *       "quantity": 30
     *     }
     *   ]
     * }
     */
    public function store() {
        $data = Request::body();

        if (!is_array($data)) {
            Response::json(['error' => 'Invalid request body'], 400);
        }

        try {
            $prescriptionId = $this->prescriptionService->createPrescription(
                $data
            );

            Response::json(
                ['message' => 'Prescription created', 'prescription_id' => $prescriptionId],
                201
            );
        } catch (Exception $e) {
            Response::json(['error' => $e->getMessage()], $e->getCode() ?: 500);
        }
    }

    /**
     * PUT /prescriptions/{id}
     * Update prescription (providers can update notes).
     *
     * Request body:
     * {
     *   "notes": "updated notes"
     * }
     */
    public function update($params) {
        $data = Request::body();

        if (!is_array($data)) {
            Response::json(['error' => 'Invalid request body'], 400);
        }

        try {
            $this->prescriptionService->updatePrescription(
                $params['id'],
                $data
            );

            Response::json(['message' => 'Prescription updated'], 200);
        } catch (Exception $e) {
            Response::json(['error' => $e->getMessage()], $e->getCode() ?: 500);
        }
    }

    /**
     * PUT /prescriptions/{id}/status
     * Update prescription status (admin/system operations).
     *
     * Request body:
     * {
     *   "status": "VERIFIED" or "DISPENSED"
     * }
     */
    public function updateStatus($params) {
        $data = Request::body();

        if (!is_array($data)) {
            Response::json(['error' => 'Invalid request body'], 400);
        }

        try {
            $this->prescriptionService->updatePrescriptionStatus(
                $params['id'],
                $data
            );

            Response::json(['message' => 'Prescription status updated'], 200);
        } catch (Exception $e) {
            Response::json(['error' => $e->getMessage()], $e->getCode() ?: 500);
        }
    }

    // ================================================================
    // PRESCRIPTION ITEMS MANAGEMENT
    // ================================================================

    /**
     * POST /prescriptions/{id}/items
     * Add a new item to a prescription.
     *
     * Request body:
     * {
     *   "medicine_name": "Aspirin",
     *   "dosage": "500mg",
     *   "quantity": 30
     * }
     */
    public function addItem($params) {
        $data = Request::body();

        if (!is_array($data)) {
            Response::json(['error' => 'Invalid request body'], 400);
        }

        try {
            $itemId = $this->prescriptionService->addItem(
                $params['id'],
                $data
            );

            Response::json(
                ['message' => 'Item added', 'item_id' => $itemId],
                201
            );
        } catch (Exception $e) {
            Response::json(['error' => $e->getMessage()], $e->getCode() ?: 500);
        }
    }

    /**
     * PUT /prescriptions/{id}/items/{item_id}
     * Update a prescription item.
     *
     * Request body:
     * {
     *   "medicine_name": "Aspirin",
     *   "dosage": "500mg",
     *   "quantity": 30
     * }
     */
    public function updateItem($params) {
        $data = Request::body();

        if (!is_array($data)) {
            Response::json(['error' => 'Invalid request body'], 400);
        }

        try {
            $this->prescriptionService->updateItem(
                $params['id'],
                $params['item_id'],
                $data
            );

            Response::json(['message' => 'Item updated'], 200);
        } catch (Exception $e) {
            Response::json(['error' => $e->getMessage()], $e->getCode() ?: 500);
        }
    }

    /**
     * DELETE /prescriptions/{id}/items/{item_id}
     * Delete a prescription item.
     */
    public function deleteItem($params) {
        try {
            $this->prescriptionService->deleteItem(
                $params['id'],
                $params['item_id']
            );

            Response::json(['message' => 'Item deleted'], 200);
        } catch (Exception $e) {
            Response::json(['error' => $e->getMessage()], $e->getCode() ?: 500);
        }
    }

    // ================================================================
    // PHARMACY OPERATIONS (PHARMACIST APIs)
    // ================================================================

    /**
     * POST /prescriptions/{id}/verify
     * Verify a prescription (pharmacist action).
     * Changes status from PENDING -> VERIFIED.
     */
    public function verify($params) {
        try {
            $this->prescriptionService->verifyPrescription(
                $params['id']
            );

            Response::json(['message' => 'Prescription verified'], 200);
        } catch (Exception $e) {
            Response::json(['error' => $e->getMessage()], $e->getCode() ?: 500);
        }
    }

    /**
     * POST /prescriptions/{id}/dispense
     * Dispense a prescription (pharmacist action).
     * Changes status from VERIFIED -> DISPENSED.
     * After dispensing, prescription is complete.
     */
    public function dispense($params) {
        try {
            $this->prescriptionService->dispensePrescription(
                $params['id']
            );

            Response::json(['message' => 'Prescription dispensed'], 200);
        } catch (Exception $e) {
            Response::json(['error' => $e->getMessage()], $e->getCode() ?: 500);
        }
    }
}

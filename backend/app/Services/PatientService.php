<?php

namespace App\Services;

use App\Repositories\PatientRepository;
use Exception;

class PatientService {

    private $patientRepo;

    public function __construct() {
        $this->patientRepo = new PatientRepository();
    }

    // ----------------------------------------------------------------
    // Called by PatientController@index
    // GET /api/patients
    // ----------------------------------------------------------------
    public function listPatients($tenantId) {
        return $this->patientRepo->findAll((int) $tenantId);
    }

    // ----------------------------------------------------------------
    // Called by PatientController@show
    // GET /api/patients/{id}
    // ----------------------------------------------------------------
    public function getPatient($id, $tenantId) {
        $patient = $this->patientRepo->findById((int) $id, (int) $tenantId);

        if (!$patient) {
            throw new Exception('Patient not found', 404);
        }

        return $patient;
    }

    // ----------------------------------------------------------------
    // Called by PatientController@store
    // POST /api/patients
    // ----------------------------------------------------------------
    public function addPatient($data, $tenantId, $userId) {
        // Validate required fields
        $required = ['name', 'dob', 'gender', 'phone', 'email'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new Exception("Field '$field' is required", 422);
            }
        }

        return $this->patientRepo->create($data, (int) $tenantId, (int) $userId);
    }

    // ----------------------------------------------------------------
    // Called by PatientController@update
    // PUT /api/patients/{id}
    // ----------------------------------------------------------------
    public function updatePatient($id, $data, $tenantId) {
        $existing = $this->patientRepo->findById((int) $id, (int) $tenantId);

        if (!$existing) {
            throw new Exception('Patient not found', 404);
        }

        $updated = $this->patientRepo->update((int) $id, (int) $tenantId, $data);

        if (!$updated) {
            throw new Exception('No changes were made', 422);
        }

        return true;
    }

    // ----------------------------------------------------------------
    // Called by PatientController@destroy
    // DELETE /api/patients/{id}
    // ----------------------------------------------------------------
    public function deletePatient($id, $tenantId) {
        $existing = $this->patientRepo->findById((int) $id, (int) $tenantId);

        if (!$existing) {
            throw new Exception('Patient not found', 404);
        }

        $deleted = $this->patientRepo->delete((int) $id, (int) $tenantId);

        if (!$deleted) {
            throw new Exception('Patient could not be deleted', 500);
        }

        return true;
    }
}
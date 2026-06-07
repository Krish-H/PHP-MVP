<?php

namespace App\Services;

<<<<<<< Updated upstream
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
=======
use App\Config\Env;
use App\Repositories\PatientRepository;
use App\Security\AES;
use Exception;

class PatientService {
    private $patientRepo;
    private $aesKey;

    /** PHI fields that must be encrypted at rest */
    private const PHI_FIELDS = [
        'name', 'dob', 'gender', 'phone', 'email',
        'address', 'blood_group', 'medical_history', 'emergency_contact',
    ];

    /** Required fields on create */
    private const REQUIRED_FIELDS = ['name', 'dob', 'gender', 'phone', 'email'];

    public function __construct() {
        $this->patientRepo = new PatientRepository();
        $this->aesKey = Env::get('AES_KEY');
    }

    // ----------------------------------------------------------------
    // Public API
    // ----------------------------------------------------------------

    public function addPatient(array $data, int $tenantId, int $userId): int {
        $this->validateRequired($data, self::REQUIRED_FIELDS);

        $encrypted = $this->encryptPhi($data, false);

        return $this->patientRepo->create($encrypted, $tenantId, $userId);
    }

    public function getPatient(int $id, int $tenantId): array {
        $row = $this->patientRepo->findById($id, $tenantId);

        if (!$row) {
            throw new Exception('Patient not found', 404);
        }

        return $this->decryptPhi($row);
    }

    public function listPatients(int $tenantId): array {
        $rows = $this->patientRepo->findAll($tenantId);

        return array_map([$this, 'decryptPhi'], $rows);
    }

    public function updatePatient(int $id, array $data, int $tenantId): void {
        // Confirm patient exists and belongs to this tenant
        $existing = $this->patientRepo->findById($id, $tenantId);
        if (!$existing) {
            throw new Exception('Patient not found', 404);
        }

        // Encrypt only the PHI fields present in the update payload
        $encrypted = $this->encryptPhi($data, true);

        $updated = $this->patientRepo->update($id, $encrypted, $tenantId);

        if (!$updated) {
            throw new Exception('No changes were made', 400);
        }
    }

    public function deletePatient(int $id, int $tenantId): void {
        $existing = $this->patientRepo->findById($id, $tenantId);
        if (!$existing) {
            throw new Exception('Patient not found', 404);
        }

        $deleted = $this->patientRepo->softDelete($id, $tenantId);

        if (!$deleted) {
            throw new Exception('Failed to delete patient', 500);
        }
    }

    // ----------------------------------------------------------------
    // Private helpers
    // ----------------------------------------------------------------

    /**
     * Encrypt PHI fields in the data array.
     *
     * @param bool $onlyPresent  When true, only encrypts keys that exist in $data
     *                           (for partial updates). When false, encrypts all PHI
     *                           fields present in $data (for create).
     */
    private function encryptPhi(array $data, bool $onlyPresent): array {
        foreach (self::PHI_FIELDS as $field) {
            if ($onlyPresent && !array_key_exists($field, $data)) {
                continue;
            }

            if (isset($data[$field]) && $data[$field] !== null) {
                $data[$field] = AES::encrypt((string) $data[$field], $this->aesKey);
            }
        }

        return $data;
    }

    /**
     * Decrypt PHI fields in a row fetched from the DB.
     * Non-PHI columns (id, tenant_id, is_deleted, timestamps) pass through unchanged.
     */
    private function decryptPhi(array $row): array {
        foreach (self::PHI_FIELDS as $field) {
            if (isset($row[$field]) && $row[$field] !== null) {
                $decrypted = AES::decrypt($row[$field], $this->aesKey);
                $row[$field] = ($decrypted !== false) ? $decrypted : null;
            }
        }

        return $row;
    }

    /**
     * Ensure all required keys are present and non-empty.
     */
    private function validateRequired(array $data, array $required): void {
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new Exception("Missing required field: $field", 422);
            }
        }
    }
}
>>>>>>> Stashed changes

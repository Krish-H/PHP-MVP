<?php

namespace App\Repositories;

use App\Config\Database;
use App\Config\Env;
use App\Security\AES;

/**
 * PatientRepository
 *
 * Handles all SQL for the `patients` table.
 * PHI fields are encrypted on write and decrypted on read here
 * so the Service layer always works with plain-text values.
 *
 * PHI fields: name, dob, gender, phone, email,
 *             address, blood_group, medical_history, emergency_contact
 */
class PatientRepository {

    private $db;
    private $key;

    public function __construct() {
        $this->db  = Database::getConnection();
        // AES key loaded from .env — same key your TL uses project-wide
        $this->key = Env::get('AES_KEY');
    }

    // ----------------------------------------------------------------
    // READ
    // ----------------------------------------------------------------

    /**
     * Get all active (non-deleted) patients for a tenant.
     *
     * @param  int   $tenantId
     * @return array  Array of decrypted patient rows
     */
    public function findAll(int $tenantId): array {
        $stmt = $this->db->prepare(
            'SELECT * FROM patients
             WHERE tenant_id  = :tenant_id
               AND is_deleted = 0
             ORDER BY created_at DESC'
        );
        $stmt->execute(['tenant_id' => $tenantId]);
        $patients = $stmt->fetchAll();

        // Decrypt PHI fields on every row before returning
        return array_map(function($patient) {
            $patient['name']              = AES::decrypt($patient['name'],              $this->key);
            $patient['dob']               = AES::decrypt($patient['dob'],               $this->key);
            $patient['gender']            = AES::decrypt($patient['gender'],            $this->key);
            $patient['phone']             = AES::decrypt($patient['phone'],             $this->key);
            $patient['email']             = AES::decrypt($patient['email'],             $this->key);
            $patient['address']           = !empty($patient['address'])           ? AES::decrypt($patient['address'],           $this->key) : null;
            $patient['blood_group']       = !empty($patient['blood_group'])       ? AES::decrypt($patient['blood_group'],       $this->key) : null;
            $patient['medical_history']   = !empty($patient['medical_history'])   ? AES::decrypt($patient['medical_history'],   $this->key) : null;
            $patient['emergency_contact'] = !empty($patient['emergency_contact']) ? AES::decrypt($patient['emergency_contact'], $this->key) : null;
            return $patient;
        }, $patients);
    }

    /**
     * Find a single patient by ID, scoped to the tenant.
     *
     * @param  int $id
     * @param  int $tenantId
     * @return array|null  Decrypted row, or null if not found
     */
    public function findById(int $id, int $tenantId): ?array {
        $stmt = $this->db->prepare(
            'SELECT * FROM patients
             WHERE id         = :id
               AND tenant_id  = :tenant_id
               AND is_deleted = 0
             LIMIT 1'
        );
        $stmt->execute(['id' => $id, 'tenant_id' => $tenantId]);
        $patient = $stmt->fetch();

        if (!$patient) {
            return null;
        }

        // Decrypt PHI fields before returning
        $patient['name']              = AES::decrypt($patient['name'],              $this->key);
        $patient['dob']               = AES::decrypt($patient['dob'],               $this->key);
        $patient['gender']            = AES::decrypt($patient['gender'],            $this->key);
        $patient['phone']             = AES::decrypt($patient['phone'],             $this->key);
        $patient['email']             = AES::decrypt($patient['email'],     
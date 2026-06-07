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
        $patient['email']             = AES::decrypt($patient['email'],             $this->key);
        $patient['address']           = !empty($patient['address'])           ? AES::decrypt($patient['address'],           $this->key) : null;
        $patient['blood_group']       = !empty($patient['blood_group'])       ? AES::decrypt($patient['blood_group'],       $this->key) : null;
        $patient['medical_history']   = !empty($patient['medical_history'])   ? AES::decrypt($patient['medical_history'],   $this->key) : null;
        $patient['emergency_contact'] = !empty($patient['emergency_contact']) ? AES::decrypt($patient['emergency_contact'], $this->key) : null;

        return $patient;
    }

    /**
     * Insert a new patient record (PHI encrypted before saving).
     *
     * @param  array $data  Plain-text patient data from the Service
     * @param  int   $tenantId
     * @param  int   $userId  The users.id this patient account is linked to
     * @return int   Newly inserted patient ID
     */
    public function create(array $data, int $tenantId, int $userId): int {
        $stmt = $this->db->prepare(
            'INSERT INTO patients
                (tenant_id, user_id,
                 name, dob, gender, phone, email,
                 address, blood_group, medical_history, emergency_contact)
             VALUES
                (:tenant_id, :user_id,
                 :name, :dob, :gender, :phone, :email,
                 :address, :blood_group, :medical_history, :emergency_contact)'
        );

        $stmt->execute([
            'tenant_id'         => $tenantId,
            'user_id'           => $userId,
            // Required PHI — encrypt before storing
            'name'              => AES::encrypt($data['name'],   $this->key),
            'dob'               => AES::encrypt($data['dob'],    $this->key),
            'gender'            => AES::encrypt($data['gender'], $this->key),
            'phone'             => AES::encrypt($data['phone'],  $this->key),
            'email'             => AES::encrypt($data['email'],  $this->key),
            // Nullable PHI — encrypt only if present
            'address'           => !empty($data['address'])           ? AES::encrypt($data['address'],           $this->key) : null,
            'blood_group'       => !empty($data['blood_group'])       ? AES::encrypt($data['blood_group'],       $this->key) : null,
            'medical_history'   => !empty($data['medical_history'])   ? AES::encrypt($data['medical_history'],   $this->key) : null,
            'emergency_contact' => !empty($data['emergency_contact']) ? AES::encrypt($data['emergency_contact'], $this->key) : null,
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Update an existing patient record.
     * Only fields present in $data are updated (PATCH-style).
     *
     * @param  int   $id
     * @param  int   $tenantId
     * @param  array $data  Plain-text fields to update
     * @return bool  True if at least one row was affected
     */
    public function update(int $id, int $tenantId, array $data): bool {
        // Encrypt only the PHI fields that are present in $data
        $allowed = [
            'name', 'dob', 'gender', 'phone', 'email',
            'address', 'blood_group', 'medical_history', 'emergency_contact'
        ];

        $setClauses = [];
        $params     = ['id' => $id, 'tenant_id' => $tenantId];

        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $setClauses[]  = "$field = :$field";
                $params[$field] = !empty($data[$field])
                    ? AES::encrypt($data[$field], $this->key)
                    : null;
            }
        }

        if (empty($setClauses)) {
            return false; // Nothing to update
        }

        $sql = 'UPDATE patients
                SET ' . implode(', ', $setClauses) . '
                WHERE id         = :id
                  AND tenant_id  = :tenant_id
                  AND is_deleted = 0';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount() > 0;
    }

    /**
     * Soft-delete a patient (sets is_deleted = 1, records deleted_at).
     * Hard deletes are never done — medical records must be preserved.
     *
     * @param  int $id
     * @param  int $tenantId
     * @return bool
     */
    public function delete(int $id, int $tenantId): bool {
        $stmt = $this->db->prepare(
            'UPDATE patients
             SET is_deleted = 1,
                 deleted_at = NOW()
             WHERE id         = :id
               AND tenant_id  = :tenant_id
               AND is_deleted = 0'
        );
        $stmt->execute(['id' => $id, 'tenant_id' => $tenantId]);

        return $stmt->rowCount() > 0;
    }
}
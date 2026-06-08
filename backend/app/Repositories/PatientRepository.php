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
        $this->key = Env::get('AES_KEY');
    }

    // ----------------------------------------------------------------
    // READ
    // ----------------------------------------------------------------

    public function findAll(int $tenantId): array {
        $stmt = $this->db->prepare(
            'SELECT * FROM patients
             WHERE tenant_id  = :tenant_id
               AND is_deleted = 0
             ORDER BY created_at DESC'
        );
        $stmt->execute(['tenant_id' => $tenantId]);
        $patients = $stmt->fetchAll();

        return array_map(function ($patient) {
            return $this->decryptPhi($patient);
        }, $patients);
    }

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

        return $this->decryptPhi($patient);
    }

    public function findByUserId(int $userId, int $tenantId): ?array {
        $stmt = $this->db->prepare(
            'SELECT * FROM patients
             WHERE user_id    = :user_id
               AND tenant_id  = :tenant_id
               AND is_deleted = 0
             LIMIT 1'
        );
        $stmt->execute(['user_id' => $userId, 'tenant_id' => $tenantId]);
        $patient = $stmt->fetch();

        if (!$patient) {
            return null;
        }

        return $this->decryptPhi($patient);
    }

    public function findByPatientUserId(int $userId, int $tenantId): ?array {
        $stmt = $this->db->prepare(
            'SELECT * FROM patients
             WHERE patient_user_id = :patient_user_id
               AND tenant_id  = :tenant_id
               AND is_deleted = 0
             LIMIT 1'
        );
        $stmt->execute(['patient_user_id' => $userId, 'tenant_id' => $tenantId]);
        $patient = $stmt->fetch();

        if (!$patient) {
            return null;
        }

        return $this->decryptPhi($patient);
    }

    public function isPatientUserLinkedToAnother(int $patientUserId, int $tenantId, ?int $excludePatientId = null): bool {
        $sql = 'SELECT COUNT(*) FROM patients
                WHERE patient_user_id = :patient_user_id
                  AND tenant_id = :tenant_id
                  AND is_deleted = 0';
        $params = ['patient_user_id' => $patientUserId, 'tenant_id' => $tenantId];

        if ($excludePatientId !== null) {
            $sql .= ' AND id != :exclude_id';
            $params['exclude_id'] = $excludePatientId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return (int)$stmt->fetchColumn() > 0;
    }

    // ----------------------------------------------------------------
    // WRITE
    // ----------------------------------------------------------------

    public function create(array $data, int $tenantId, int $userId): int {
        $stmt = $this->db->prepare(
            'INSERT INTO patients
             (tenant_id, user_id, patient_user_id, name, dob, gender, phone, email,
              address, blood_group, medical_history, emergency_contact,
              created_at, updated_at)
             VALUES
             (:tenant_id, :user_id, :patient_user_id, :name, :dob, :gender, :phone, :email,
              :address, :blood_group, :medical_history, :emergency_contact,
              NOW(), NOW())'
        );

        $stmt->execute([
            'tenant_id'         => $tenantId,
            'user_id'           => $userId,
            'patient_user_id'   => !empty($data['patient_user_id']) ? (int) $data['patient_user_id'] : null,
            'name'              => AES::encrypt($data['name'],   $this->key),
            'dob'               => AES::encrypt($data['dob'],    $this->key),
            'gender'            => AES::encrypt($data['gender'], $this->key),
            'phone'             => AES::encrypt($data['phone'],  $this->key),
            'email'             => AES::encrypt($data['email'],  $this->key),
            'address'           => !empty($data['address'])           ? AES::encrypt($data['address'],           $this->key) : null,
            'blood_group'       => !empty($data['blood_group'])       ? AES::encrypt($data['blood_group'],       $this->key) : null,
            'medical_history'   => !empty($data['medical_history'])   ? AES::encrypt($data['medical_history'],   $this->key) : null,
            'emergency_contact' => !empty($data['emergency_contact']) ? AES::encrypt($data['emergency_contact'], $this->key) : null,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, int $tenantId, array $data): bool {
        $setClauses = [];
        $params     = ['id' => $id, 'tenant_id' => $tenantId];

        $phiFields = ['name', 'dob', 'gender', 'phone', 'email',
                      'address', 'blood_group', 'medical_history', 'emergency_contact'];

        foreach ($data as $field => $value) {
            if (in_array($field, $phiFields, true)) {
                $setClauses[]   = "$field = :$field";
                $params[$field] = AES::encrypt((string) $value, $this->key);
            } elseif ($field === 'patient_user_id') {
                $setClauses[]   = "$field = :$field";
                $params[$field] = !empty($value) ? (int) $value : null;
            } else {
                $setClauses[]   = "$field = :$field";
                $params[$field] = $value;
            }
        }

        if (empty($setClauses)) {
            return false;
        }

        $setClauses[] = 'updated_at = NOW()';
        $sql = 'UPDATE patients SET ' . implode(', ', $setClauses) .
               ' WHERE id = :id AND tenant_id = :tenant_id AND is_deleted = 0';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount() > 0;
    }

    /**
     * Soft-delete: sets is_deleted = 1 and records deleted_at timestamp.
     */
    public function delete(int $id, int $tenantId): bool {
        $stmt = $this->db->prepare(
            'UPDATE patients
             SET is_deleted = 1, deleted_at = NOW()
             WHERE id = :id AND tenant_id = :tenant_id AND is_deleted = 0'
        );
        $stmt->execute(['id' => $id, 'tenant_id' => $tenantId]);

        return $stmt->rowCount() > 0;
    }

    // ----------------------------------------------------------------
    // PRIVATE HELPERS
    // ----------------------------------------------------------------

    private function decryptPhi(array $patient): array {
        foreach (['name', 'dob', 'gender', 'phone', 'email'] as $field) {
            $patient[$field] = AES::decrypt($patient[$field], $this->key);
        }

        foreach (['address', 'blood_group', 'medical_history', 'emergency_contact'] as $field) {
            $patient[$field] = !empty($patient[$field])
                ? AES::decrypt($patient[$field], $this->key)
                : null;
        }

        return $patient;
    }
}

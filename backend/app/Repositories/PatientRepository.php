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

    public function getPatientStats(): array {
        $stats = [
            'total' => 0,
            'active' => 0,
            'new_this_month' => 0,
            'added_today' => 0
        ];

        $stmt = $this->db->query("SELECT COUNT(*) FROM patients WHERE is_deleted = 0");
        $stats['total'] = (int) $stmt->fetchColumn();
        $stats['active'] = $stats['total']; // all non-deleted are considered active

        $stmtMonth = $this->db->query("SELECT COUNT(*) FROM patients WHERE is_deleted = 0 AND MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())");
        $stats['new_this_month'] = (int) $stmtMonth->fetchColumn();

        $stmtToday = $this->db->query("SELECT COUNT(*) FROM patients WHERE is_deleted = 0 AND DATE(created_at) = CURRENT_DATE()");
        $stats['added_today'] = (int) $stmtToday->fetchColumn();

        return $stats;
    }

    public function findAll(int $page = 1, int $limit = 10, string $search = '', string $gender = '', string $status = ''): array {
        $stmt = $this->db->prepare(
            'SELECT id, user_id, patient_user_id, name, dob, gender, phone, email, address, blood_group, medical_history, emergency_contact, created_at, updated_at FROM patients
             WHERE is_deleted = 0
             ORDER BY created_at DESC'
        );
        $stmt->execute();
        $patients = $stmt->fetchAll();

        // Decrypt all
        $decryptedPatients = array_map(function ($patient) {
            return $this->decryptPhi($patient);
        }, $patients);

        // Filter in PHP due to encryption
        $filtered = array_filter($decryptedPatients, function($p) use ($search, $gender, $status) {
            $matchSearch = true;
            if ($search !== '') {
                $searchLower = strtolower($search);
                $nameMatch = (isset($p['name']) && strpos(strtolower($p['name']), $searchLower) !== false);
                $idMatch = (strpos((string)$p['id'], $search) !== false);
                $phoneMatch = (isset($p['phone']) && strpos($p['phone'], $search) !== false);
                $matchSearch = $nameMatch || $idMatch || $phoneMatch;
            }
            
            $matchGender = true;
            if ($gender !== '' && $gender !== 'all') {
                $matchGender = (isset($p['gender']) && $p['gender'] === $gender);
            }
            
            $matchStatus = true;
            if ($status !== '' && $status !== 'all') {
                $matchStatus = (isset($p['status']) && $p['status'] === $status);
            }
            
            return $matchSearch && $matchGender && $matchStatus;
        });

        // Re-index array after filter
        $filtered = array_values($filtered);
        $total = count($filtered);

        // Paginate
        $offset = ($page - 1) * $limit;
        $paginated = array_slice($filtered, $offset, $limit);

        return [
            'data' => $paginated,
            'total' => $total
        ];
    }

    public function findById(int $id): ?array {
        $stmt = $this->db->prepare(
            'SELECT id, user_id, patient_user_id, name, dob, gender, phone, email, address, blood_group, medical_history, emergency_contact, created_at, updated_at FROM patients
             WHERE id         = :id
               AND is_deleted = 0
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $patient = $stmt->fetch();

        if (!$patient) {
            return null;
        }

        return $this->decryptPhi($patient);
    }

    public function findByUserId(int $userId): ?array {
        $stmt = $this->db->prepare(
            'SELECT id, user_id, patient_user_id, name, dob, gender, phone, email, address, blood_group, medical_history, emergency_contact, created_at, updated_at FROM patients
             WHERE user_id    = :user_id
               AND is_deleted = 0
             LIMIT 1'
        );
        $stmt->execute(['user_id' => $userId]);
        $patient = $stmt->fetch();

        if (!$patient) {
            return null;
        }

        return $this->decryptPhi($patient);
    }

    public function findByPatientUserId(int $userId): ?array {
        $stmt = $this->db->prepare(
            'SELECT id, user_id, patient_user_id, name, dob, gender, phone, email, address, blood_group, medical_history, emergency_contact, created_at, updated_at FROM patients
             WHERE patient_user_id = :patient_user_id
               AND is_deleted = 0
             LIMIT 1'
        );
        $stmt->execute(['patient_user_id' => $userId]);
        $patient = $stmt->fetch();

        if (!$patient) {
            return null;
        }

        return $this->decryptPhi($patient);
    }

    public function isPatientUserLinkedToAnother(int $patientUserId, ?int $excludePatientId = null): bool {
        $sql = 'SELECT COUNT(*) FROM patients
                WHERE patient_user_id = :patient_user_id
                  AND is_deleted = 0';
        $params = ['patient_user_id' => $patientUserId];

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

    public function create(array $data, int $userId): int {
        $stmt = $this->db->prepare(
            'INSERT INTO patients
             (user_id, patient_user_id, name, dob, gender, phone, email,
              address, blood_group, medical_history, emergency_contact,
              created_at, updated_at)
             VALUES
             (:user_id, :patient_user_id, :name, :dob, :gender, :phone, :email,
              :address, :blood_group, :medical_history, :emergency_contact,
              NOW(), NOW())'
        );

        $stmt->execute([
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

    public function update(int $id, array $data): bool {
        $setClauses = [];
        $params     = ['id' => $id];

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
               ' WHERE id = :id AND is_deleted = 0';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount() > 0;
    }

    /**
     * Soft-delete: sets is_deleted = 1 and records deleted_at timestamp.
     */
    public function delete(int $id): bool {
        $stmt = $this->db->prepare(
            'UPDATE patients
             SET is_deleted = 1, deleted_at = NOW()
             WHERE id = :id AND is_deleted = 0'
        );
        $stmt->execute(['id' => $id]);

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

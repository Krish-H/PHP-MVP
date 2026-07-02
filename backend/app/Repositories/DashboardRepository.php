<?php

namespace App\Repositories;

use App\Config\Database;

class DashboardRepository {
    private $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    public function countPatients() {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM patients WHERE is_deleted = 0');
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    public function countAppointments() {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM appointments');
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    public function countAppointmentsByStatus() {
        $stmt = $this->db->prepare('SELECT status, COUNT(*) AS count FROM appointments GROUP BY status');
        $stmt->execute();

        $result = [];
        foreach ($stmt->fetchAll() as $row) {
            $result[$row['status']] = (int) $row['count'];
        }

        return $result;
    }

    public function getRecentAppointments($limit = 5) {
        $stmt = $this->db->prepare('
            SELECT a.id, a.patient_id, p.name as patient_name, a.provider_id, u.name as provider_name, a.appointment_date, a.appointment_time, a.status, a.notes 
            FROM appointments a
            LEFT JOIN patients p ON a.patient_id = p.id
            LEFT JOIN users u ON a.provider_id = u.id
            WHERE a.is_cancelled = 0
            ORDER BY a.appointment_date DESC, a.appointment_time DESC
            LIMIT :limit
        ');
        $stmt->bindValue(':limit', (int)$limit, \PDO::PARAM_INT);
        $stmt->execute();
        
        $results = $stmt->fetchAll();
        
        // Decrypt patient name using AES if it's fetched and not null
        $key = \App\Config\Env::get('AES_KEY');
        foreach ($results as &$row) {
            if (!empty($row['patient_name'])) {
                $decrypted = \App\Security\AES::decrypt($row['patient_name'], $key);
                $row['patient_name'] = $decrypted !== false ? $decrypted : null;
            }
        }
        
        return $results;
    }
}


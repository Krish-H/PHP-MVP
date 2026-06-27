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
}


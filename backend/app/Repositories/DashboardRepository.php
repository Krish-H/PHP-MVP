<?php

namespace App\Repositories;

use App\Config\Database;

class DashboardRepository {
    private $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    public function countPatients($tenantId) {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM patients WHERE tenant_id = :tenant_id AND is_deleted = 0');
        $stmt->execute(['tenant_id' => $tenantId]);
        return (int) $stmt->fetchColumn();
    }

    public function countAppointments($tenantId) {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM appointments WHERE tenant_id = :tenant_id');
        $stmt->execute(['tenant_id' => $tenantId]);
        return (int) $stmt->fetchColumn();
    }

    public function countAppointmentsByStatus($tenantId) {
        $stmt = $this->db->prepare('SELECT status, COUNT(*) AS count FROM appointments WHERE tenant_id = :tenant_id GROUP BY status');
        $stmt->execute(['tenant_id' => $tenantId]);

        $result = [];
        foreach ($stmt->fetchAll() as $row) {
            $result[$row['status']] = (int) $row['count'];
        }

        return $result;
    }
}


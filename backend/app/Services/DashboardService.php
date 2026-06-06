<?php

namespace App\Services;

use App\Repositories\DashboardRepository;
use Exception;

class DashboardService {
    private $dashboardRepo;

    public function __construct() {
        $this->dashboardRepo = new DashboardRepository();
    }

    public function getDashboardMetrics($tenantId) {
        if (empty($tenantId)) {
            throw new Exception('Tenant context is required', 400);
        }

        $totalPatients = $this->dashboardRepo->countPatients($tenantId);
        $totalAppointments = $this->dashboardRepo->countAppointments($tenantId);
        $appointmentsByStatus = $this->dashboardRepo->countAppointmentsByStatus($tenantId);

        return [
            'total_patients' => $totalPatients,
            'total_appointments' => $totalAppointments,
            'scheduled_appointments' => $appointmentsByStatus['scheduled'] ?? 0,
            'completed_appointments' => $appointmentsByStatus['completed'] ?? 0,
            'cancelled_appointments' => $appointmentsByStatus['cancelled'] ?? 0
        ];
    }
}

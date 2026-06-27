<?php

namespace App\Services;

use App\Repositories\DashboardRepository;
use Exception;

class DashboardService {
    private $dashboardRepo;

    public function __construct() {
        $this->dashboardRepo = new DashboardRepository();
    }

    public function getDashboardMetrics() {
        $totalPatients = $this->dashboardRepo->countPatients();
        $totalAppointments = $this->dashboardRepo->countAppointments();
        $appointmentsByStatus = $this->dashboardRepo->countAppointmentsByStatus();

        return [
            'total_patients' => $totalPatients,
            'total_appointments' => $totalAppointments,
            'scheduled_appointments' => $appointmentsByStatus['scheduled'] ?? 0,
            'completed_appointments' => $appointmentsByStatus['completed'] ?? 0,
            'cancelled_appointments' => $appointmentsByStatus['cancelled'] ?? 0
        ];
    }
}

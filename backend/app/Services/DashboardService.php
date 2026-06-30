<?php

namespace App\Services;

use App\Repositories\DashboardRepository;
use App\Repositories\BillingRepository;
use Exception;

class DashboardService {
    private $dashboardRepo;
    private $billingRepo;

    public function __construct() {
        $this->dashboardRepo = new DashboardRepository();
        $this->billingRepo = new BillingRepository();
    }

    public function getDashboardMetrics() {
        $totalPatients = $this->dashboardRepo->countPatients();
        $totalAppointments = $this->dashboardRepo->countAppointments();
        $appointmentsByStatus = $this->dashboardRepo->countAppointmentsByStatus();

        $pendingSummary = $this->billingRepo->getPendingSummary();
        $paidSummary = $this->billingRepo->getPaidSummary();
        
        $totalInvoicesCount = $pendingSummary['pending_count'] + $paidSummary['paid_count'];
        $totalRevenue = $paidSummary['paid_amount'] + $pendingSummary['pending_amount'];

        return [
            'total_patients' => $totalPatients,
            'total_appointments' => $totalAppointments,
            'scheduled_appointments' => $appointmentsByStatus['scheduled'] ?? 0,
            'completed_appointments' => $appointmentsByStatus['completed'] ?? 0,
            'cancelled_appointments' => $appointmentsByStatus['cancelled'] ?? 0,
            'total_invoices' => $totalInvoicesCount,
            'pending_invoices' => $pendingSummary['pending_count'],
            'total_revenue' => $totalRevenue,
            'pending_amount' => $pendingSummary['pending_amount']
        ];
    }
}

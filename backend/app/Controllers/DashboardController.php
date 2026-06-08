<?php

namespace App\Controllers;

use App\Helpers\Response;
use App\Services\DashboardService;
use Exception;

class DashboardController {
    private $dashboardService;

    public function __construct() {
        $this->dashboardService = new DashboardService();
    }

    public function index() {
        $tenantId = $_SESSION['current_tenant_id'] ?? null;

        try {
            $dashboard = $this->dashboardService->getDashboardMetrics($tenantId);
            Response::json(['dashboard' => $dashboard], 200);
        } catch (Exception $e) {
            $statusCode = (is_numeric($e->getCode()) && $e->getCode() >= 100 && $e->getCode() < 600) ? (int) $e->getCode() : 500;
            Response::json(['error' => $e->getMessage()], $statusCode);
        }
    }
}

<?php

namespace App\Controllers;

use App\Helpers\Response;
use App\Services\CalendarService;
use Exception;

class CalendarController {

    private $calendarService;

    public function __construct() {
        $this->calendarService = new CalendarService();
    }

    /**
     * GET /api/calendar
     *
     * Two modes via query params:
     *   ?date=YYYY-MM-DD                          → single day
     *   ?start_date=YYYY-MM-DD&end_date=YYYY-MM-DD → date range
     */
    public function index() {
        $tenantId = (int) $_SESSION['current_tenant_id'];
        $roleId   = (int) $_SESSION['current_role_id'];
        $userId   = (int) $_SESSION['current_user_id'];
        $params   = $_GET;

        try {
            if (isset($params['date'])) {
                $data = $this->calendarService->getByDate($params, $tenantId, $roleId, $userId);
            } elseif (isset($params['start_date']) || isset($params['end_date'])) {
                $data = $this->calendarService->getByRange($params, $tenantId, $roleId, $userId);
            } else {
                Response::json(['error' => 'Provide "date" or "start_date" & "end_date" as query params'], 422);
                return;
            }

            Response::json(['appointments' => $data, 'count' => count($data)], 200);
        } catch (Exception $e) {
            Response::json(['error' => $e->getMessage()], $e->getCode() ?: 500);
        }
    }

    /**
     * GET /api/calendar/appointments/{id}/tooltip
     */
    public function tooltip($params) {
        $tenantId = (int) $_SESSION['current_tenant_id'];
        $roleId   = (int) $_SESSION['current_role_id'];
        $userId   = (int) $_SESSION['current_user_id'];

        try {
            $data = $this->calendarService->getTooltip((int) $params['id'], $tenantId, $roleId, $userId);
            Response::json(['tooltip' => $data], 200);
        } catch (Exception $e) {
            Response::json(['error' => $e->getMessage()], $e->getCode() ?: 500);
        }
    }
}

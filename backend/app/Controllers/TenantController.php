<?php

namespace App\Controllers;

use App\Helpers\Request;
use App\Helpers\Response;
use App\Services\TenantService;
use Exception;

class TenantController {
    private $tenantService;

    public function __construct() {
        $this->tenantService = new TenantService();
    }

    public function register() {
        $data = Request::body();

        if (!is_array($data)) {
            Response::json(['error' => 'Invalid request body'], 400);
        }

        try {
            $result = $this->tenantService->createTenant($data);
            Response::json($result, 201);
        } catch (Exception $e) {
            $code = $e->getCode();
            $statusCode = (is_numeric($code) && $code >= 100 && $code < 600) ? (int)$code : 500;
            Response::json(['error' => $e->getMessage()], $statusCode);
        }
    }
}

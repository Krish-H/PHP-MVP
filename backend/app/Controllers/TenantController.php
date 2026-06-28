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
    public function updateTheme() {
        $data = Request::body();

        if (!isset($data['theme']) || !is_array($data['theme'])) {
            Response::json(['error' => 'Invalid theme payload'], 400);
        }

        try {
            $tenantId = $_SESSION['tenant_id'] ?? null;
            if (!$tenantId) {
                Response::json(['error' => 'Tenant context missing'], 400);
            }

            $result = $this->tenantService->updateTheme($tenantId, $data['theme']);
            Response::json([
                'success' => true,
                'message' => 'Theme updated successfully',
                'theme' => $result
            ], 200);
        } catch (Exception $e) {
            $code = $e->getCode();
            $statusCode = (is_numeric($code) && $code >= 100 && $code < 600) ? (int)$code : 500;
            Response::json(['error' => $e->getMessage()], $statusCode);
        }
    }

    public function getTheme() {
        try {
            $themeConfig = $_SESSION['theme_config'] ?? null;
            
            if (is_string($themeConfig)) {
                $themeConfig = json_decode($themeConfig, true);
            }

            Response::json([
                'success' => true,
                'theme' => $themeConfig ?: new \stdClass()
            ], 200);
        } catch (Exception $e) {
            $code = $e->getCode();
            $statusCode = (is_numeric($code) && $code >= 100 && $code < 600) ? (int)$code : 500;
            Response::json(['error' => $e->getMessage()], $statusCode);
        }
    }
}

<?php

namespace App\Controllers;

use App\Helpers\Request;
use App\Helpers\Response;
use App\Services\BillingService;
use Exception;

class BillingController {
    private $billingService;

    public function __construct() {
        $this->billingService = new BillingService();
    }

    public function index() {
        $invoices = $this->billingService->listInvoices($_SESSION['current_tenant_id']);
        Response::json(['invoices' => $invoices], 200);
    }

    public function store() {
        $data = Request::body();

        if (!is_array($data)) {
            Response::json(['error' => 'Invalid request body'], 400);
        }

        try {
            $invoiceId = $this->billingService->createInvoice($data, $_SESSION['current_tenant_id']);
            Response::json(['message' => 'Invoice generated', 'invoice_id' => $invoiceId], 201);
        } catch (Exception $e) {
            Response::json(['error' => $e->getMessage()], $e->getCode() ?: 500);
        }
    }

    public function update($params) {
        $data = Request::body();

        if (!is_array($data)) {
            Response::json(['error' => 'Invalid request body'], 400);
        }

        try {
            $this->billingService->updatePaymentStatus($params['id'], $data, $_SESSION['current_tenant_id']);
            Response::json(['message' => 'Invoice status updated'], 200);
        } catch (Exception $e) {
            Response::json(['error' => $e->getMessage()], $e->getCode() ?: 500);
        }
    }
}

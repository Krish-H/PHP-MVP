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
        $invoices = $this->billingService->listInvoices();
        Response::json(['invoices' => $invoices], 200);
    }

    public function myInvoices() {
        try {
            $invoices = $this->billingService->getMyInvoices(
                $_SESSION['current_user_id']
            );
            Response::json(['invoices' => $invoices], 200);
        } catch (Exception $e) {
            Response::json(['error' => $e->getMessage()], $e->getCode() ?: 500);
        }
    }

    public function pendingSummary() {
        try {
            $summary = $this->billingService->getPendingSummary();
            Response::json($summary, 200);
        } catch (Exception $e) {
            Response::json(['error' => $e->getMessage()], $e->getCode() ?: 500);
        }
    }

    public function paidSummary() {
        try {
            $summary = $this->billingService->getPaidSummary();
            Response::json($summary, 200);
        } catch (Exception $e) {
            Response::json(['error' => $e->getMessage()], $e->getCode() ?: 500);
        }
    }

    public function store() {
        $data = Request::body();

        if (!is_array($data)) {
            Response::json(['error' => 'Invalid request body'], 400);
        }

        try {
            $invoiceId = $this->billingService->createInvoice($data);
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
            $this->billingService->updatePaymentStatus($params['id'], $data);
            Response::json(['message' => 'Invoice status updated'], 200);
        } catch (Exception $e) {
            Response::json(['error' => $e->getMessage()], $e->getCode() ?: 500);
        }
    }
}

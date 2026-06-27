<?php

namespace App\Middleware;

use App\Repositories\TenantRepository;
use App\Config\Database;
use App\Helpers\Response;

class TenantMiddleware {
    public function handle() {
        $host = $_SERVER['HTTP_HOST'] ?? '';
        
        // Remove port if exists (e.g., localhost:3000)
        $host = preg_replace('/:\d+$/', '', $host);
        
        $parts = explode('.', $host);
        
        // e.g. apollo.localhost or apollo.example.com
        if (count($parts) < 2 || $parts[0] === 'www' || $host === 'localhost') {
            Response::json(['error' => 'Tenant subdomain is required'], 400);
        }

        $subdomain = $parts[0];

        $tenantRepo = new TenantRepository();
        $tenant = $tenantRepo->findBySubdomain($subdomain);

        if (!$tenant) {
            Response::json(['error' => 'Tenant not found'], 404);
        }

        // Switch connection to the tenant database
        Database::connectTenant($tenant['db_name']);
        
        // Store the tenant info for the request lifecycle
        $_SESSION['tenant_id'] = $tenant['tenant_id'];
        $_SESSION['tenant_db'] = $tenant['db_name'];
    }
}

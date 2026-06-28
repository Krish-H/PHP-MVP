<?php

namespace App\Repositories;

use App\Config\Database;
use PDO;

class TenantRepository {

    private function getMasterConnection() {
        // Ensure we are connected to the master database for tenant queries
        Database::connectMaster();
        return Database::getConnection();
    }

    public function createTenant($data) {
        $pdo = $this->getMasterConnection();
        $stmt = $pdo->prepare("
            INSERT INTO tenants (tenant_id, company_name, subdomain, db_name, status)
            VALUES (?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $data['tenant_id'],
            $data['company_name'],
            $data['subdomain'],
            $data['db_name'],
            $data['status'] ?? 'active'
        ]);

        return $pdo->lastInsertId();
    }

    public function findBySubdomain($subdomain) {
        $pdo = $this->getMasterConnection();
        $stmt = $pdo->prepare("SELECT * FROM tenants WHERE subdomain = ? AND status = 'active' LIMIT 1");
        $stmt->execute([$subdomain]);
        return $stmt->fetch();
    }

    public function findByTenantId($tenantId) {
        $pdo = $this->getMasterConnection();
        $stmt = $pdo->prepare("SELECT * FROM tenants WHERE tenant_id = ? AND status = 'active' LIMIT 1");
        $stmt->execute([$tenantId]);
        return $stmt->fetch();
    }
    public function updateTheme($tenantId, $themeConfig) {
        $pdo = $this->getMasterConnection();
        $stmt = $pdo->prepare("
            UPDATE tenants
            SET theme_config = :theme
            WHERE tenant_id = :tenant_id
        ");
        
        return $stmt->execute([
            'theme' => $themeConfig,
            'tenant_id' => $tenantId
        ]);
    }
}

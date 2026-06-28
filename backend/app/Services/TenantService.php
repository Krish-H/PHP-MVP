<?php

namespace App\Services;

use App\Repositories\TenantRepository;
use App\Repositories\UserRepository;
use App\Config\Database;
use App\Config\Roles;
use Exception;
use PDO;

class TenantService {
    private $tenantRepo;

    public function __construct() {
        $this->tenantRepo = new TenantRepository();
    }

    public function createTenant($data) {
        $companyName = trim($data['company_name'] ?? '');
        $email = trim($data['email'] ?? '');
        $password = $data['password'] ?? '';
        
        if (empty($companyName) || empty($email) || empty($password)) {
            throw new Exception('Company name, email, and password are required', 400);
        }

        // 1. Generate Subdomain
        $subdomain = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $companyName));
        
        // Ensure subdomain uniqueness
        if ($this->tenantRepo->findBySubdomain($subdomain)) {
            $subdomain .= rand(100, 999);
        }

        // 2. Generate Tenant UUID and DB Name
        $tenantId = $this->generateUuid();
        $dbName = 'tenant_' . $subdomain . '_db';

        // 3. Register in master_db
        $tenantData = [
            'tenant_id' => $tenantId,
            'company_name' => $companyName,
            'subdomain' => $subdomain,
            'db_name' => $dbName,
            'status' => 'active'
        ];
        
        $this->tenantRepo->createTenant($tenantData);

        // 4. Create the new tenant database
        $this->createDatabase($dbName);

        // 5. Clone the schema from php_mvp_db to the new database
        $this->cloneSchema('php_mvp_db', $dbName);

        // 6. Insert the admin user into the new tenant database
        Database::connectTenant($dbName);
        $userRepo = new UserRepository();
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        // Pass Roles::ADMIN directly
        $userRepo->create($email, $hashedPassword, Roles::ADMIN, 1, $companyName . ' Admin');

        return [
            'success' => true,
            'tenant_url' => 'http://' . $subdomain . '.lvh.me:3000',
            'tenant_id' => $tenantId
        ];
    }

    private function generateUuid() {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }

    private function createDatabase($dbName) {
        // Connect to master or just without specific db
        Database::connectMaster();
        $pdo = Database::getConnection();
        
        $stmt = $pdo->prepare("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $stmt->execute();
    }

    private function cloneSchema($sourceDb, $targetDb) {
        Database::connectTenant($sourceDb);
        $pdoSource = Database::getConnection();

        // Get all tables from source
        $stmt = $pdoSource->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $createStatements = [];
        foreach ($tables as $table) {
            $stmt = $pdoSource->query("SHOW CREATE TABLE `$table`");
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $createStatements[] = $row['Create Table'];
        }

        // Switch to target DB and execute creates
        Database::connectTenant($targetDb);
        $pdoTarget = Database::getConnection();

        // Disable foreign key checks during schema cloning to avoid order issues
        $pdoTarget->exec("SET FOREIGN_KEY_CHECKS = 0");
        
        foreach ($createStatements as $sql) {
            $pdoTarget->exec($sql);
        }
        
        $pdoTarget->exec("SET FOREIGN_KEY_CHECKS = 1");

        // Seed roles with explicit IDs to match App\Config\Roles
        $roles = [
            1 => 'Admin',
            2 => 'Provider',
            3 => 'Nurse',
            4 => 'Patient',
            5 => 'Pharmacist',
            6 => 'Receptionist'
        ];
        $stmt = $pdoTarget->prepare("INSERT INTO roles (id, name) VALUES (?, ?)");
        foreach ($roles as $id => $name) {
            $stmt->execute([$id, $name]);
        }
    }
    public function updateTheme($tenantId, $theme) {
        $allowedFields = ['mode', 'primaryColor', 'secondaryColor', 'fontFamily', 'borderRadius'];
        $filteredTheme = [];

        foreach ($allowedFields as $field) {
            if (isset($theme[$field])) {
                $filteredTheme[$field] = $theme[$field];
            }
        }

        $themeJson = json_encode($filteredTheme);
        
        $this->tenantRepo->updateTheme($tenantId, $themeJson);

        // Update current session to reflect changes immediately
        $_SESSION['theme_config'] = $themeJson;

        return $filteredTheme;
    }
}

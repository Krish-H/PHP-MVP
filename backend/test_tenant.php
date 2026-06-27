<?php
spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $base_dir = __DIR__ . '/app/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) { return; }
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    if (file_exists($file)) { require $file; }
});

use App\Config\Env;
Env::load(__DIR__ . '/.env');

use App\Services\TenantService;
use App\Config\Database;

try {
    $service = new TenantService();
    $result = $service->createTenant([
        'company_name' => 'Apollo Hospital',
        'email' => 'admin@apollo.com',
        'password' => 'SecurePass123',
        'plan' => 'premium'
    ]);
    
    echo "Tenant Created:\n";
    print_r($result);
    
    echo "\nTesting connection to new database...\n";
    Database::switchDatabase('tenant_apollohospital_db');
    $pdo = Database::getConnection();
    
    $stmt = $pdo->query("SELECT * FROM users LIMIT 1");
    $user = $stmt->fetch();
    
    echo "First user in new DB:\n";
    print_r($user);
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n" . $e->getTraceAsString();
}

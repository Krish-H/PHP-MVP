<?php
error_reporting(0);
ini_set('display_errors', 0);

session_start(); // Session for CSRF and Access Token

// Basic autoloader for our app directory
spl_autoload_register(function ($class) {
    // Convert namespace to full file path
    $prefix = 'App\\';
    $base_dir = __DIR__ . '/../app/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});

use App\Config\Env;
use App\Core\Router;

// Load .env variables
Env::load(__DIR__ . '/../.env');

// Handle CORS

$allowedOrigins = [
    "http://localhost:3000",
    "http://lvh.me:3000"
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

if (in_array($origin, $allowedOrigins) || preg_match('/^http:\/\/[a-z0-9-]+\.lvh\.me:3000$/', $origin)) {
    header("Access-Control-Allow-Origin: $origin");
}

header("Access-Control-Allow-Credentials: true");

header(
    "Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS"
);

header(
    "Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-TOKEN"
);

header("Access-Control-Max-Age: 86400");


if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Initialize Router
$router = new Router();
require __DIR__ . '/../app/Routes/api.php';

$urlParam = $_GET['url'] ?? null;
if ($urlParam) {
    $uri = '/' . rtrim($urlParam, '/');
} else {
    $uri = rtrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
    if ($uri === '') {
        $uri = '/';
    }
}

$router->dispatch($uri, $_SERVER['REQUEST_METHOD']);
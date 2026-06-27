<?php

namespace App\Config;

use PDO;
use PDOException;

class Database {
    private static $instance = null;
    private $connection;
    private $currentDb = null;

    private function __construct($dbName = null) {
        $host = Env::get('DB_HOST', 'localhost');
        $db   = $dbName ?? Env::get('DB_NAME', 'php_mvp_db');
        $user = Env::get('DB_USER', 'root');
        $pass = Env::get('DB_PASS', '');

        $dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $this->connection = new PDO($dsn, $user, $pass, $options);
            $this->currentDb = $db;
        } catch (PDOException $e) {
            // Log error internally, do not expose to user
            die(json_encode(['error' => 'Database connection failed']));
        }
    }

    public static function getConnection() {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance->connection;
    }

    public static function connectTenant($dbName) {
        if (self::$instance !== null && self::$instance->currentDb === $dbName) {
            return;
        }
        self::$instance = new Database($dbName);
    }

    public static function connectMaster() {
        if (self::$instance !== null && self::$instance->currentDb === 'master_db') {
            return;
        }
        self::$instance = new Database('master_db');
    }
}

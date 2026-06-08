<?php

require __DIR__ . '/vendor/autoload.php';

use App\Config\Database;

try {
    $db = Database::getConnection();
    
    // Add column, FK, and unique index
    $sql = "ALTER TABLE patients 
            ADD COLUMN patient_user_id INT NULL AFTER user_id, 
            ADD CONSTRAINT fk_patient_user FOREIGN KEY (patient_user_id) REFERENCES users(id), 
            ADD UNIQUE KEY uk_patient_user (patient_user_id)";
            
    $db->exec($sql);
    echo "Migration successful!\n";
} catch (PDOException $e) {
    if ($e->getCode() == '42S21' || strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "Column already exists.\n";
    } else {
        echo "Migration failed: " . $e->getMessage() . "\n";
    }
}

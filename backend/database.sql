-- ============================================================
-- PHP-MVP Healthcare Database Schema
-- ============================================================

CREATE DATABASE IF NOT EXISTS php_mvp_db;
USE php_mvp_db;

-- ============================================================
-- TABLE: tenants
-- ============================================================

CREATE TABLE IF NOT EXISTS tenants (
id INT AUTO_INCREMENT PRIMARY KEY,
name VARCHAR(255) NOT NULL ,
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO tenants (name)
SELECT 'Default Hospital'
WHERE NOT EXISTS (
SELECT 1 FROM tenants WHERE name = 'Default Hospital'
);

-- ============================================================
-- TABLE: roles
-- ============================================================

CREATE TABLE IF NOT EXISTS roles (
id INT AUTO_INCREMENT PRIMARY KEY,
name VARCHAR(50) NOT NULL UNIQUE,
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO roles (name)
SELECT 'Admin'
WHERE NOT EXISTS (SELECT 1 FROM roles WHERE name = 'Admin');

INSERT INTO roles (name)
SELECT 'Provider'
WHERE NOT EXISTS (SELECT 1 FROM roles WHERE name = 'Provider');

INSERT INTO roles (name)
SELECT 'Nurse'
WHERE NOT EXISTS (SELECT 1 FROM roles WHERE name = 'Nurse');

INSERT INTO roles (name)
SELECT 'Patient'
WHERE NOT EXISTS (SELECT 1 FROM roles WHERE name = 'Patient');

INSERT INTO roles (name)
SELECT 'Pharmacist'
WHERE NOT EXISTS (SELECT 1 FROM roles WHERE name = 'Pharmacist');

INSERT INTO roles (name)
SELECT 'Receptionist'
WHERE NOT EXISTS (SELECT 1 FROM roles WHERE name = 'Receptionist');

-- ============================================================
-- TABLE: users
-- ============================================================

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    role_id INT NOT NULL,
    email VARCHAR(255) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_active TINYINT(1) DEFAULT 1,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    name VARCHAR(100) DEFAULT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ============================================================
-- TABLE: refresh_tokens
-- ============================================================

CREATE TABLE IF NOT EXISTS refresh_tokens (
id INT AUTO_INCREMENT PRIMARY KEY,

user_id INT NOT NULL,

token_hash VARCHAR(255) NOT NULL,
expires_at TIMESTAMP NOT NULL,

revoked TINYINT(1) DEFAULT 0,

created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

INDEX idx_refresh_user (user_id),
INDEX idx_refresh_token (token_hash),

CONSTRAINT fk_refresh_user
    FOREIGN KEY (user_id)
    REFERENCES users(id)
    ON DELETE CASCADE


);

-- ============================================================
-- TABLE: patients
-- PHI FIELDS STORED ENCRYPTED
-- ============================================================

CREATE TABLE IF NOT EXISTS patients (
id INT AUTO_INCREMENT PRIMARY KEY,

tenant_id INT NOT NULL,
user_id INT NOT NULL,

name TEXT NOT NULL,                 -- encrypted
dob TEXT NOT NULL,                  -- encrypted
gender TEXT NOT NULL,               -- encrypted
phone TEXT NOT NULL,                -- encrypted
email TEXT NOT NULL,                -- encrypted

address TEXT NULL,                  -- encrypted
blood_group TEXT NULL,              -- encrypted
medical_history LONGTEXT NULL,      -- encrypted
emergency_contact TEXT NULL,        -- encrypted

is_deleted TINYINT(1) DEFAULT 0,
deleted_at TIMESTAMP NULL,

created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ON UPDATE CURRENT_TIMESTAMP,

INDEX idx_patients_tenant (tenant_id),
INDEX idx_patients_user (user_id),

CONSTRAINT fk_patients_tenant
    FOREIGN KEY (tenant_id)
    REFERENCES tenants(id),

CONSTRAINT fk_patients_user
    FOREIGN KEY (user_id)
    REFERENCES users(id)
    ON DELETE CASCADE


);

-- ============================================================
-- TABLE: appointments
-- ============================================================

CREATE TABLE IF NOT EXISTS appointments (
id INT AUTO_INCREMENT PRIMARY KEY,


tenant_id INT NOT NULL,
patient_id INT NOT NULL,
provider_id INT NOT NULL,

appointment_date DATE NOT NULL,
appointment_time TIME NOT NULL,

status ENUM(
    'scheduled',
    'completed',
    'cancelled'
) NOT NULL DEFAULT 'scheduled',

notes TEXT NULL,

is_cancelled TINYINT(1) DEFAULT 0,

created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ON UPDATE CURRENT_TIMESTAMP,

INDEX idx_appt_tenant (tenant_id),
INDEX idx_appt_patient (patient_id),
INDEX idx_appt_provider (provider_id),
INDEX idx_appt_date (appointment_date),
INDEX idx_appt_tenant_date (tenant_id, appointment_date),

UNIQUE KEY uk_provider_slot (
    provider_id,
    appointment_date,
    appointment_time
),

CONSTRAINT fk_appt_tenant
    FOREIGN KEY (tenant_id)
    REFERENCES tenants(id),

CONSTRAINT fk_appt_patient
    FOREIGN KEY (patient_id)
    REFERENCES patients(id)
    ON DELETE RESTRICT,

CONSTRAINT fk_appt_provider
    FOREIGN KEY (provider_id)
    REFERENCES users(id)
    ON DELETE RESTRICT


);

-- ============================================================
<<<<<<< HEAD
-- TABLE: invoices
-- ============================================================

CREATE TABLE IF NOT EXISTS invoices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    patient_id INT NOT NULL,
    invoice_number VARCHAR(50) NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    status ENUM('pending', 'paid') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_invoice_tenant (tenant_id),
    INDEX idx_invoice_patient (patient_id),
    UNIQUE KEY uk_invoice_number (tenant_id, invoice_number),
    CONSTRAINT fk_invoice_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    CONSTRAINT fk_invoice_patient FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE RESTRICT
);

-- ============================================================
-- TABLE: payments
-- ============================================================

CREATE TABLE IF NOT EXISTS payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT NOT NULL,
    payment_amount DECIMAL(10, 2) NOT NULL,
    payment_status VARCHAR(50) NOT NULL DEFAULT 'completed',
    paid_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_payment_invoice FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE
=======
-- appointment_notes table (Communication Module)
-- ============================================================

CREATE TABLE IF NOT EXISTS appointment_notes (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id      INT         NOT NULL,
    appointment_id INT         NOT NULL,
    user_id        INT         NOT NULL,  -- who wrote the note
    note           TEXT        NOT NULL,  -- AES-256-CBC encrypted
    is_deleted     TINYINT(1)  NOT NULL DEFAULT 0,
    deleted_at     TIMESTAMP   NULL      DEFAULT NULL,
    created_at     TIMESTAMP   NOT NULL  DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP   NOT NULL  DEFAULT CURRENT_TIMESTAMP
                               ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_notes_tenant      (tenant_id),
    INDEX idx_notes_appointment (appointment_id),
    INDEX idx_notes_user        (user_id),

    CONSTRAINT fk_notes_tenant
        FOREIGN KEY (tenant_id)
        REFERENCES tenants(id),

    CONSTRAINT fk_notes_appointment
        FOREIGN KEY (appointment_id)
        REFERENCES appointments(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_notes_user
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE RESTRICT
>>>>>>> 1d06e1bd3891379bccbf2a3271016aed8bc5cd96
);

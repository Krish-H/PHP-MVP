# PHP-MVP Healthcare Management System - API Documentation

This document serves as a complete Postman-friendly API reference for the PHP-MVP Healthcare Management System.

All APIs are prefixed with the base URL:
```http
http://localhost/PHP-MVP/backend/public
```

---

## Table of Contents
1. [Authentication & Tenant Management](#authentication--tenant-management)
2. [User Management](#user-management)
3. [Patient Management](#patient-management)
4. [Appointment Management](#appointment-management)
5. [Dashboard](#dashboard)
6. [Calendar API](#calendar-api)
7. [Prescription & Pharmacy](#prescription--pharmacy)
8. [Billing & Payment](#billing--payment)
9. [Communication Module](#communication-module)
10. [Staff Management](#staff-management)

---

## Authentication & Tenant Management

### Register

#### Method
```http
POST
```

#### URL
```http
http://localhost/PHP-MVP/backend/public/api/register
```

#### Headers
```json
{
  "Content-Type": "application/json"
}
```

#### Request Body
```json
{
  "name": "Admin User",
  "email": "admin@example.com",
  "password": "Password123",
  "role_id": 1,
  "tenant_id": 1
}
```

#### Success Response
```json
{
  "message": "Registration successful",
  "user_id": 1
}
```

#### Notes
* Authentication: Not required
* Allowed Roles: N/A
* Validation Rules: Email must be unique. Password required. Tenant ID is required.

---

### Login

#### Method
```http
POST
```

#### URL
```http
http://localhost/PHP-MVP/backend/public/api/login
```

#### Headers
```json
{
  "Content-Type": "application/json"
}
```

#### Request Body
```json
{
  "email": "admin@example.com",
  "password": "Password123"
}
```

#### Success Response
```json
{
  "message": "Login successful",
  "user": {
    "id": 1,
    "name": "Admin User",
    "email": "admin@example.com",
    "role_id": 1,
    "tenant_id": 1
  },
  "access_token": "eyJhbGciOiJIUzI1Ni...",
  "refresh_token": "def456...",
  "csrf_token": "a1b2c3d4e5f6..."
}
```

#### Notes
* Authentication: Not required
* Allowed Roles: N/A
* A `refresh_token` will also be set as an `HttpOnly` cookie automatically in Postman.

---

### Refresh Token

#### Method
```http
POST
```

#### URL
```http
http://localhost/PHP-MVP/backend/public/api/refresh
```

#### Headers
```json
{
  "Content-Type": "application/json"
}
```

#### Request Body
*(No body required. Refresh token is read from HttpOnly cookie)*

#### Success Response
```json
{
  "message": "Token refreshed",
  "access_token": "eyJhbGciOiJIUzI1Ni..."
}
```

#### Notes
* Authentication: Cookie-based
* Allowed Roles: All users

---

### Logout

#### Method
```http
POST
```

#### URL
```http
http://localhost/PHP-MVP/backend/public/api/logout
```

#### Headers
```json
{
  "Authorization": "Bearer <access_token>",
  "Content-Type": "application/json",
  "X-CSRF-TOKEN": "<csrf_token>"
}
```

#### Request Body
*(Empty)*

#### Success Response
```json
{
  "message": "Logged out successfully"
}
```

#### Notes
* Authentication: Required
* Allowed Roles: All users

---

### Profile

#### Method
```http
GET
```

#### URL
```http
http://localhost/PHP-MVP/backend/public/api/profile
```

#### Headers
```json
{
  "Authorization": "Bearer <access_token>",
  "Content-Type": "application/json"
}
```

#### Success Response
```json
{
  "message": "Profile retrieved",
  "user": {
    "id": 1,
    "name": "Admin User",
    "email": "admin@example.com",
    "role_id": 1,
    "tenant_id": 1
  }
}
```

#### Notes
* Authentication: Required
* Allowed Roles: All users

---

### Change Password

#### Method
```http
POST
```

#### URL
```http
http://localhost/PHP-MVP/backend/public/api/change-password
```

#### Headers
```json
{
  "Authorization": "Bearer <access_token>",
  "Content-Type": "application/json"
}
```

#### Request Body
```json
{
  "current_password": "Password123",
  "new_password": "NewPassword123",
  "confirm_password": "NewPassword123"
}
```

#### Success Response
```json
{
  "success": true,
  "message": "Password changed successfully"
}
```

#### Notes
* Authentication: Required
* Allowed Roles: All users

---

## User Management

### List Users

#### Method
```http
GET
```

#### URL
```http
http://localhost/PHP-MVP/backend/public/api/users?page=1&limit=10&name=John&email=john@test.com
```

#### Headers
```json
{
  "Authorization": "Bearer <access_token>",
  "Content-Type": "application/json"
}
```

#### Success Response
```json
{
  "data": [
    {
      "id": 2,
      "name": "Jane Doe",
      "email": "jane@example.com",
      "role_id": 3
    }
  ],
  "pagination": {
    "total": 1,
    "page": 1,
    "limit": 10,
    "total_pages": 1
  }
}
```

#### Notes
* Authentication: Required
* Allowed Roles: Admin

---

### Get User

#### Method
```http
GET
```

#### URL
```http
http://localhost/PHP-MVP/backend/public/api/users/2
```

#### Headers
```json
{
  "Authorization": "Bearer <access_token>",
  "Content-Type": "application/json"
}
```

#### Success Response
```json
{
  "id": 2,
  "name": "Jane Doe",
  "email": "jane@example.com",
  "role_id": 3
}
```

#### Notes
* Authentication: Required
* Allowed Roles: Admin

---

### Create User

#### Method
```http
POST
```

#### URL
```http
http://localhost/PHP-MVP/backend/public/api/users
```

#### Headers
```json
{
  "Authorization": "Bearer <access_token>",
  "Content-Type": "application/json",
  "X-CSRF-TOKEN": "<csrf_token>"
}
```

#### Request Body
```json
{
  "name": "Jane Doe",
  "email": "jane@example.com",
  "password": "Password123",
  "role": 3
}
```

#### Success Response
```json
{
  "message": "User created",
  "user_id": 2
}
```

#### Notes
* Authentication: Required
* Allowed Roles: Admin
* Validation Rules: Email must be unique within the tenant.

---

### Update User

#### Method
```http
PUT
```

#### URL
```http
http://localhost/PHP-MVP/backend/public/api/users/2
```

#### Headers
```json
{
  "Authorization": "Bearer <access_token>",
  "Content-Type": "application/json",
  "X-CSRF-TOKEN": "<csrf_token>"
}
```

#### Request Body
```json
{
  "name": "Jane Doe Updated",
  "email": "jane_updated@example.com",
  "role": 4
}
```

#### Success Response
```json
{
  "message": "User updated successfully"
}
```

#### Notes
* Authentication: Required
* Allowed Roles: Admin

---

### Delete User

#### Method
```http
DELETE
```

#### URL
```http
http://localhost/PHP-MVP/backend/public/api/users/2
```

#### Headers
```json
{
  "Authorization": "Bearer <access_token>",
  "Content-Type": "application/json",
  "X-CSRF-TOKEN": "<csrf_token>"
}
```

#### Request Body
*(Empty)*

#### Success Response
```json
{
  "message": "User deleted successfully"
}
```

#### Notes
* Authentication: Required
* Allowed Roles: Admin
* Validation Rules: Uses soft-delete (`deleted_at`). Cannot delete yourself.

---

## Patient Management

### List Patients

#### Method
```http
GET
```

#### URL
```http
http://localhost/PHP-MVP/backend/public/api/patients
```

#### Headers
```json
{
  "Authorization": "Bearer <access_token>",
  "Content-Type": "application/json"
}
```

#### Success Response
```json
[
  {
    "id": 1,
    "name": "John Doe",
    "dob": "1990-01-01",
    "gender": "Male",
    "phone": "555-0100",
    "email": "john@example.com"
  }
]
```

#### Notes
* Authentication: Required
* Allowed Roles: Provider, Nurse

---

### Get Patient

#### Method
```http
GET
```

#### URL
```http
http://localhost/PHP-MVP/backend/public/api/patients/1
```

#### Headers
```json
{
  "Authorization": "Bearer <access_token>",
  "Content-Type": "application/json"
}
```

#### Success Response
```json
{
  "id": 1,
  "name": "John Doe",
  "dob": "1990-01-01",
  "gender": "Male",
  "phone": "555-0100",
  "email": "john@example.com",
  "address": "123 Main St",
  "blood_group": "O+",
  "medical_history": "None",
  "emergency_contact": "555-0101",
  "patient_user_id": 25
}
```

#### Notes
* Authentication: Required
* Allowed Roles: Provider, Nurse
* DB fields are fully encrypted (AES-256-CBC) but decypted in JSON payload.

---

### Create Patient

#### Method
```http
POST
```

#### URL
```http
http://localhost/PHP-MVP/backend/public/api/patients
```

#### Headers
```json
{
  "Authorization": "Bearer <access_token>",
  "Content-Type": "application/json",
  "X-CSRF-TOKEN": "<csrf_token>"
}
```

#### Request Body
```json
{
  "name": "John Doe",
  "dob": "1990-01-01",
  "gender": "Male",
  "phone": "555-0100",
  "email": "john@example.com",
  "address": "123 Main St",
  "blood_group": "O+",
  "medical_history": "No known allergies",
  "emergency_contact": "555-0101",
  "patient_user_id": 25
}
```

#### Success Response
```json
{
  "message": "Patient created successfully",
  "patient_id": 1
}
```

#### Notes
* Authentication: Required
* Allowed Roles: Provider, Nurse
* Validation Rules: `name`, `dob`, `gender`, `phone`, `email` are required. `patient_user_id` must belong to a user with `PATIENT` role in the same tenant and cannot be already mapped.

---

### Update Patient

#### Method
```http
PUT
```

#### URL
```http
http://localhost/PHP-MVP/backend/public/api/patients/1
```

#### Headers
```json
{
  "Authorization": "Bearer <access_token>",
  "Content-Type": "application/json",
  "X-CSRF-TOKEN": "<csrf_token>"
}
```

#### Request Body
```json
{
  "phone": "555-9999",
  "address": "456 New St"
}
```

#### Success Response
```json
{
  "message": "Patient updated successfully"
}
```

#### Notes
* Authentication: Required
* Allowed Roles: Provider, Nurse

---

### Delete Patient

#### Method
```http
DELETE
```

#### URL
```http
http://localhost/PHP-MVP/backend/public/api/patients/1
```

#### Headers
```json
{
  "Authorization": "Bearer <access_token>",
  "Content-Type": "application/json",
  "X-CSRF-TOKEN": "<csrf_token>"
}
```

#### Request Body
*(Empty)*

#### Success Response
```json
{
  "message": "Patient deleted successfully"
}
```

#### Notes
* Authentication: Required
* Allowed Roles: Provider, Nurse
* Validation Rules: Soft deletes via `is_deleted` flag.

---

## Appointment Management

### List Appointments

#### Method
```http
GET
```

#### URL
```http
http://localhost/PHP-MVP/backend/public/api/appointments
```

#### Headers
```json
{
  "Authorization": "Bearer <access_token>",
  "Content-Type": "application/json"
}
```

#### Success Response
```json
[
  {
    "id": 1,
    "patient_id": 1,
    "provider_id": 2,
    "appointment_date": "2026-06-15",
    "appointment_time": "10:00:00",
    "status": "scheduled"
  }
]
```

#### Notes
* Authentication: Required
* Allowed Roles: Provider, Nurse, Patient

---

### Get Appointment

#### Method
```http
GET
```

#### URL
```http
http://localhost/PHP-MVP/backend/public/api/appointments/1
```

#### Headers
```json
{
  "Authorization": "Bearer <access_token>",
  "Content-Type": "application/json"
}
```

#### Success Response
```json
{
  "id": 1,
  "patient_id": 1,
  "provider_id": 2,
  "appointment_date": "2026-06-15",
  "appointment_time": "10:00:00",
  "status": "scheduled",
  "notes": "Follow-up checkup."
}
```

#### Notes
* Authentication: Required
* Allowed Roles: Provider, Nurse, Patient

---

### Create Appointment

#### Method
```http
POST
```

#### URL
```http
http://localhost/PHP-MVP/backend/public/api/appointments
```

#### Headers
```json
{
  "Authorization": "Bearer <access_token>",
  "Content-Type": "application/json",
  "X-CSRF-TOKEN": "<csrf_token>"
}
```

#### Request Body
```json
{
  "patient_id": 1,
  "provider_id": 2,
  "appointment_date": "2026-06-15",
  "appointment_time": "10:00:00",
  "notes": "Follow-up checkup"
}
```

#### Success Response
```json
{
  "message": "Appointment created",
  "appointment_id": 1
}
```

#### Error Response
```json
{
  "error": "Provider already has an appointment at this date and time"
}
```

#### Notes
* Authentication: Required
* Allowed Roles: Provider, Nurse, Patient
* Validation Rules: Strong double-booking prevention via `slotExists` validation.

---

### Update Appointment

#### Method
```http
PUT
```

#### URL
```http
http://localhost/PHP-MVP/backend/public/api/appointments/1
```

#### Headers
```json
{
  "Authorization": "Bearer <access_token>",
  "Content-Type": "application/json",
  "X-CSRF-TOKEN": "<csrf_token>"
}
```

#### Request Body
```json
{
  "status": "completed",
  "notes": "Patient fully recovered. No further action needed."
}
```

#### Success Response
```json
{
  "message": "Appointment updated successfully"
}
```

#### Notes
* Authentication: Required
* Allowed Roles: Provider, Nurse, Patient
* Notes are strictly stored securely via AES-256-CBC.

---

### Cancel Appointment

#### Method
```http
DELETE
```

#### URL
```http
http://localhost/PHP-MVP/backend/public/api/appointments/1
```

#### Headers
```json
{
  "Authorization": "Bearer <access_token>",
  "Content-Type": "application/json",
  "X-CSRF-TOKEN": "<csrf_token>"
}
```

#### Request Body
*(Empty)*

#### Success Response
```json
{
  "message": "Appointment deleted successfully"
}
```

#### Notes
* Authentication: Required
* Allowed Roles: Provider, Nurse, Patient
* Marks as `is_cancelled = 1` and status = `cancelled`.

---

## Dashboard

### Dashboard Metrics

#### Method
```http
GET
```

#### URL
```http
http://localhost/PHP-MVP/backend/public/api/dashboard
```

#### Headers
```json
{
  "Authorization": "Bearer <access_token>",
  "Content-Type": "application/json"
}
```

#### Success Response
```json
{
  "dashboard": {
    "total_patients": 25,
    "total_appointments": 150,
    "total_invoices": 45,
    "pending_invoices": 5,
    "appointments_by_status": [
      {
        "status": "scheduled",
        "count": 120
      },
      {
        "status": "completed",
        "count": 30
      }
    ]
  }
}
```

#### Notes
* Authentication: Required
* Allowed Roles: Admin, Provider

---

## Calendar API

### Calendar Events

#### Method
```http
GET
```

#### URL
```http
http://localhost/PHP-MVP/backend/public/api/calendar?start_date=2026-06-01&end_date=2026-06-30
```

#### Headers
```json
{
  "Authorization": "Bearer <access_token>",
  "Content-Type": "application/json"
}
```

#### Success Response
```json
[
  {
    "id": 1,
    "title": "Apt with John Doe",
    "start": "2026-06-15T10:00:00",
    "provider_id": 2,
    "status": "scheduled"
  }
]
```

#### Notes
* Authentication: Required
* Allowed Roles: Admin, Provider, Nurse, Receptionist
* Accepts `?date=` or `?start_date=&end_date=`.

---

### Appointment Tooltip

#### Method
```http
GET
```

#### URL
```http
http://localhost/PHP-MVP/backend/public/api/calendar/appointments/1/tooltip
```

#### Headers
```json
{
  "Authorization": "Bearer <access_token>",
  "Content-Type": "application/json"
}
```

#### Success Response
```json
{
  "id": 1,
  "patient_name": "John Doe",
  "provider_name": "Dr. Smith",
  "appointment_date": "2026-06-15",
  "appointment_time": "10:00:00",
  "status": "scheduled",
  "notes": "Follow-up checkup."
}
```

#### Notes
* Authentication: Required
* Allowed Roles: Admin, Provider, Nurse, Receptionist

---

## Prescription & Pharmacy

### List Prescriptions

#### Method
```http
GET
```

#### URL
```http
http://localhost/PHP-MVP/backend/public/api/prescriptions
```

#### Headers
```json
{
  "Authorization": "Bearer <access_token>",
  "Content-Type": "application/json"
}
```

#### Success Response
```json
[
  {
    "id": 1,
    "patient_id": 1,
    "provider_id": 2,
    "status": "PENDING",
    "notes": "Take after meals"
  }
]
```

#### Notes
* Authentication: Required
* Allowed Roles: Provider, Pharmacist, Patient

---

### Create Prescription

#### Method
```http
POST
```

#### URL
```http
http://localhost/PHP-MVP/backend/public/api/prescriptions
```

#### Headers
```json
{
  "Authorization": "Bearer <access_token>",
  "Content-Type": "application/json",
  "X-CSRF-TOKEN": "<csrf_token>"
}
```

#### Request Body
```json
{
  "patient_id": 1,
  "provider_id": 2,
  "notes": "Take after meals",
  "items": [
    {
      "medicine_name": "Amoxicillin",
      "dosage": "500mg",
      "quantity": 20
    }
  ]
}
```

#### Success Response
```json
{
  "message": "Prescription created successfully",
  "prescription_id": 1
}
```

#### Notes
* Authentication: Required
* Allowed Roles: Provider

---

### Update Prescription

#### Method
```http
PUT
```

#### URL
```http
http://localhost/PHP-MVP/backend/public/api/prescriptions/1
```

#### Headers
```json
{
  "Authorization": "Bearer <access_token>",
  "Content-Type": "application/json",
  "X-CSRF-TOKEN": "<csrf_token>"
}
```

#### Request Body
```json
{
  "notes": "Take before meals"
}
```

#### Success Response
```json
{
  "message": "Prescription updated successfully"
}
```

#### Notes
* Authentication: Required
* Allowed Roles: Provider

---

### Add Prescription Item

#### Method
```http
POST
```

#### URL
```http
http://localhost/PHP-MVP/backend/public/api/prescriptions/1/items
```

#### Headers
```json
{
  "Authorization": "Bearer <access_token>",
  "Content-Type": "application/json",
  "X-CSRF-TOKEN": "<csrf_token>"
}
```

#### Request Body
```json
{
  "medicine_name": "Paracetamol",
  "dosage": "500mg",
  "quantity": 10
}
```

#### Success Response
```json
{
  "message": "Prescription item added successfully",
  "item_id": 2
}
```

#### Notes
* Authentication: Required
* Allowed Roles: Provider

---

### Update Prescription Item

#### Method
```http
PUT
```

#### URL
```http
http://localhost/PHP-MVP/backend/public/api/prescriptions/1/items/1
```

#### Headers
```json
{
  "Authorization": "Bearer <access_token>",
  "Content-Type": "application/json",
  "X-CSRF-TOKEN": "<csrf_token>"
}
```

#### Request Body
```json
{
  "dosage": "250mg",
  "quantity": 15
}
```

#### Success Response
```json
{
  "message": "Prescription item updated successfully"
}
```

#### Notes
* Authentication: Required
* Allowed Roles: Provider

---

### Delete Prescription Item

#### Method
```http
DELETE
```

#### URL
```http
http://localhost/PHP-MVP/backend/public/api/prescriptions/1/items/1
```

#### Headers
```json
{
  "Authorization": "Bearer <access_token>",
  "Content-Type": "application/json",
  "X-CSRF-TOKEN": "<csrf_token>"
}
```

#### Request Body
*(Empty)*

#### Success Response
```json
{
  "message": "Prescription item deleted successfully"
}
```

#### Notes
* Authentication: Required
* Allowed Roles: Provider

---

### Update Prescription Status

#### Method
```http
PUT
```

#### URL
```http
http://localhost/PHP-MVP/backend/public/api/prescriptions/1/status
```

#### Headers
```json
{
  "Authorization": "Bearer <access_token>",
  "Content-Type": "application/json",
  "X-CSRF-TOKEN": "<csrf_token>"
}
```

#### Request Body
```json
{
  "status": "COMPLETED"
}
```

#### Success Response
```json
{
  "message": "Prescription status updated successfully"
}
```

#### Notes
* Authentication: Required
* Allowed Roles: Admin, Provider

---

### Verify Prescription (Pharmacist)

#### Method
```http
POST
```

#### URL
```http
http://localhost/PHP-MVP/backend/public/api/prescriptions/1/verify
```

#### Headers
```json
{
  "Authorization": "Bearer <access_token>",
  "Content-Type": "application/json",
  "X-CSRF-TOKEN": "<csrf_token>"
}
```

#### Request Body
*(Empty)*

#### Success Response
```json
{
  "message": "Prescription verified"
}
```

#### Notes
* Authentication: Required
* Allowed Roles: Pharmacist
* Moves status from `PENDING` to `VERIFIED`.

---

### Dispense Prescription (Pharmacist)

#### Method
```http
POST
```

#### URL
```http
http://localhost/PHP-MVP/backend/public/api/prescriptions/1/dispense
```

#### Headers
```json
{
  "Authorization": "Bearer <access_token>",
  "Content-Type": "application/json",
  "X-CSRF-TOKEN": "<csrf_token>"
}
```

#### Request Body
*(Empty)*

#### Success Response
```json
{
  "message": "Prescription dispensed"
}
```

#### Notes
* Authentication: Required
* Allowed Roles: Pharmacist
* Moves status from `VERIFIED` to `DISPENSED`.

---

## Billing & Payment

### Generate Invoice

#### Method
```http
POST
```

#### URL
```http
http://localhost/PHP-MVP/backend/public/api/invoices
```

#### Headers
```json
{
  "Authorization": "Bearer <access_token>",
  "Content-Type": "application/json",
  "X-CSRF-TOKEN": "<csrf_token>"
}
```

#### Request Body
```json
{
  "patient_id": 1,
  "invoice_number": "INV-1002",
  "amount": 150.00
}
```

#### Success Response
```json
{
  "message": "Invoice created successfully",
  "invoice_id": 1
}
```

#### Notes
* Authentication: Required
* Allowed Roles: Admin, Provider
* Validates `amount` > 0.

---

### List Invoices

#### Method
```http
GET
```

#### URL
```http
http://localhost/PHP-MVP/backend/public/api/invoices
```

#### Headers
```json
{
  "Authorization": "Bearer <access_token>",
  "Content-Type": "application/json"
}
```

#### Success Response
```json
{
  "invoices": [
    {
      "id": 1,
      "patient_id": 1,
      "invoice_number": "INV-1002",
      "amount": "150.00",
      "status": "pending"
    }
  ]
}
```

#### Notes
* Authentication: Required
* Allowed Roles: Admin, Provider

---

### My Invoices (Patient Only)

#### Method
```http
GET
```

#### URL
```http
http://localhost/PHP-MVP/backend/public/api/invoices/my
```

#### Headers
```json
{
  "Authorization": "Bearer <access_token>",
  "Content-Type": "application/json"
}
```

#### Success Response
```json
{
  "invoices": [
    {
      "id": 1,
      "invoice_number": "INV-1002",
      "amount": "150.00",
      "status": "pending"
    }
  ]
}
```

#### Notes
* Authentication: Required
* Allowed Roles: Patient
* Looks up the patient record based on the currently logged-in Patient mapping (`patient_user_id`).

---

### Update Invoice Status

#### Method
```http
PUT
```

#### URL
```http
http://localhost/PHP-MVP/backend/public/api/invoices/1
```

#### Headers
```json
{
  "Authorization": "Bearer <access_token>",
  "Content-Type": "application/json",
  "X-CSRF-TOKEN": "<csrf_token>"
}
```

#### Request Body
```json
{
  "status": "paid"
}
```

#### Success Response
```json
{
  "message": "Invoice updated successfully"
}
```

#### Notes
* Authentication: Required
* Allowed Roles: Admin, Provider

---

### Pending Summary

#### Method
```http
GET
```

#### URL
```http
http://localhost/PHP-MVP/backend/public/api/invoices/pending-summary
```

#### Headers
```json
{
  "Authorization": "Bearer <access_token>",
  "Content-Type": "application/json"
}
```

#### Success Response
```json
{
  "summary": {
    "count": 5,
    "total_amount": "750.00"
  }
}
```

#### Notes
* Authentication: Required
* Allowed Roles: Admin, Provider

---

### Paid Summary

#### Method
```http
GET
```

#### URL
```http
http://localhost/PHP-MVP/backend/public/api/invoices/paid-summary
```

#### Headers
```json
{
  "Authorization": "Bearer <access_token>",
  "Content-Type": "application/json"
}
```

#### Success Response
```json
{
  "summary": {
    "count": 10,
    "total_amount": "1500.00"
  }
}
```

#### Notes
* Authentication: Required
* Allowed Roles: Admin, Provider

---

## Communication Module

### Add Note

#### Method
```http
POST
```

#### URL
```http
http://localhost/PHP-MVP/backend/public/api/appointments/1/notes
```

#### Headers
```json
{
  "Authorization": "Bearer <access_token>",
  "Content-Type": "application/json",
  "X-CSRF-TOKEN": "<csrf_token>"
}
```

#### Request Body
```json
{
  "note": "Patient complained of mild headaches."
}
```

#### Success Response
```json
{
  "message": "Note added successfully",
  "note_id": 1
}
```

#### Notes
* Authentication: Required
* Allowed Roles: Provider, Nurse
* Note text is stored encrypted (AES-256-CBC).

---

### List Notes

#### Method
```http
GET
```

#### URL
```http
http://localhost/PHP-MVP/backend/public/api/appointments/1/notes
```

#### Headers
```json
{
  "Authorization": "Bearer <access_token>",
  "Content-Type": "application/json"
}
```

#### Success Response
```json
{
  "notes": [
    {
      "id": 1,
      "note": "Patient complained of mild headaches.",
      "created_at": "2026-06-15 10:15:00"
    }
  ]
}
```

#### Notes
* Authentication: Required
* Allowed Roles: Provider, Nurse
* Notes are decrypted on retrieval.

---

### Delete Note

#### Method
```http
DELETE
```

#### URL
```http
http://localhost/PHP-MVP/backend/public/api/notes/1
```

#### Headers
```json
{
  "Authorization": "Bearer <access_token>",
  "Content-Type": "application/json",
  "X-CSRF-TOKEN": "<csrf_token>"
}
```

#### Request Body
*(Empty)*

#### Success Response
```json
{
  "message": "Note deleted successfully"
}
```

#### Notes
* Authentication: Required
* Allowed Roles: Provider, Nurse

---

## Staff Management

### Add Staff

#### Method
```http
POST
```

#### URL
```http
http://localhost/PHP-MVP/backend/public/api/staff
```

#### Headers
```json
{
  "Authorization": "Bearer <access_token>",
  "Content-Type": "application/json",
  "X-CSRF-TOKEN": "<csrf_token>"
}
```

#### Request Body
```json
{
  "name": "Dr. Sarah",
  "email": "sarah@example.com",
  "password": "Password123",
  "role_id": 6
}
```

#### Success Response
```json
{
  "message": "Staff member added successfully",
  "user_id": 3
}
```

#### Notes
* Authentication: Required
* Allowed Roles: Admin
* Role ID must be a valid staff ID (e.g. 2 for Provider, 3 for Nurse).

---

### Activate/Deactivate Staff

#### Method
```http
PATCH
```

#### URL
```http
http://localhost/PHP-MVP/backend/public/api/staff/3/deactivate
```
*(Also `/api/staff/3/activate`)*

#### Headers
```json
{
  "Authorization": "Bearer <access_token>",
  "Content-Type": "application/json",
  "X-CSRF-TOKEN": "<csrf_token>"
}
```

#### Request Body
*(Empty)*

#### Success Response
```json
{
  "message": "Staff member deactivated successfully"
}
```

#### Notes
* Authentication: Required
* Allowed Roles: Admin
* Toggles the `is_active` flag.

---

### Delete Staff

#### Method
```http
DELETE
```

#### URL
```http
http://localhost/PHP-MVP/backend/public/api/staff/1
```

#### Headers
```json
{
  "Authorization": "Bearer <access_token>",
  "Content-Type": "application/json",
  "X-CSRF-TOKEN": "<csrf_token>"
}
```

#### Request Body
*(Empty)*

#### Success Response
```json
{
  "message": "Staff member deleted successfully"
}
```

#### Notes
* Authentication: Required
* Allowed Roles: Admin

---
*Generated directly from active implementation.*

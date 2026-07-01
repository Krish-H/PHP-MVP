<?php

// api.php

use App\Config\Roles;
use App\Middleware\CsrfMiddleware;
use App\Middleware\AuthMiddleware;
use App\Middleware\RoleMiddleware;
use App\Middleware\TenantMiddleware;

/** @var \App\Core\Router $router */

// SaaS routes
$router->post('/api/tenants/register', 'TenantController@register');
$router->put('/api/tenants/theme', 'TenantController@updateTheme', [TenantMiddleware::class, AuthMiddleware::class, [RoleMiddleware::class, [Roles::ADMIN]]]);
$router->get('/api/tenants/theme', 'TenantController@getTheme', [TenantMiddleware::class]);

// Public tenant routes
$router->post('/api/register', 'AuthController@register', [TenantMiddleware::class]);
$router->post('/api/login', 'AuthController@login', [TenantMiddleware::class]);
$router->post('/api/refresh', 'AuthController@refresh', [TenantMiddleware::class]);
$router->get('/api/csrf-token', 'AuthController@csrfToken', [TenantMiddleware::class]);

// Protected auth routes
$router->post('/api/logout', 'AuthController@logout', [TenantMiddleware::class, AuthMiddleware::class, CsrfMiddleware::class]);
$router->get('/api/profile', 'AuthController@profile', [TenantMiddleware::class, AuthMiddleware::class]);
$router->post('/api/change-password', 'AuthController@changePassword', [TenantMiddleware::class, AuthMiddleware::class, CsrfMiddleware::class]);
// $router->get('/api/dashboard', 'DashboardController@index', [TenantMiddleware::class, AuthMiddleware::class, [RoleMiddleware::class, [Roles::ADMIN, Roles::PROVIDER]]]);
$router->get('/api/dashboard', 'DashboardController@index', [TenantMiddleware::class, AuthMiddleware::class, [RoleMiddleware::class, [Roles::ADMIN, Roles::PROVIDER, Roles::NURSE]]]);
// Patient management
$router->get('/api/patients', 'PatientController@index', [TenantMiddleware::class, AuthMiddleware::class, [RoleMiddleware::class, [Roles::ADMIN, Roles::PROVIDER, Roles::NURSE]]]);
$router->post('/api/patients', 'PatientController@store', [TenantMiddleware::class, AuthMiddleware::class, CsrfMiddleware::class, [RoleMiddleware::class, [Roles::PROVIDER, Roles::NURSE]]]);
$router->get('/api/patients/{id}', 'PatientController@show', [TenantMiddleware::class, AuthMiddleware::class, [RoleMiddleware::class, [Roles::PROVIDER, Roles::NURSE]]]);
$router->put('/api/patients/{id}', 'PatientController@update', [TenantMiddleware::class, AuthMiddleware::class, CsrfMiddleware::class, [RoleMiddleware::class, [Roles::PROVIDER, Roles::NURSE]]]);
$router->delete('/api/patients/{id}', 'PatientController@destroy', [TenantMiddleware::class, AuthMiddleware::class, CsrfMiddleware::class, [RoleMiddleware::class, [Roles::PROVIDER, Roles::NURSE]]]);

// Appointment management
// Added Roles::RECEPTIONIST — needed for Calendar module to fetch appointments
$router->get('/api/appointments', 'AppointmentController@index', [TenantMiddleware::class, AuthMiddleware::class, [RoleMiddleware::class, [Roles::PROVIDER, Roles::NURSE, Roles::PATIENT, Roles::RECEPTIONIST]]]);
$router->post('/api/appointments', 'AppointmentController@store', [TenantMiddleware::class, AuthMiddleware::class, CsrfMiddleware::class, [RoleMiddleware::class, [Roles::PROVIDER, Roles::NURSE, Roles::PATIENT]]]);
$router->get('/api/appointments/{id}', 'AppointmentController@show', [TenantMiddleware::class, AuthMiddleware::class, [RoleMiddleware::class, [Roles::PROVIDER, Roles::NURSE, Roles::PATIENT]]]);
$router->put('/api/appointments/{id}', 'AppointmentController@update', [TenantMiddleware::class, AuthMiddleware::class, CsrfMiddleware::class, [RoleMiddleware::class, [Roles::PROVIDER, Roles::NURSE, Roles::PATIENT]]]);
$router->delete('/api/appointments/{id}', 'AppointmentController@destroy', [TenantMiddleware::class, AuthMiddleware::class, CsrfMiddleware::class, [RoleMiddleware::class, [Roles::PROVIDER, Roles::NURSE, Roles::PATIENT]]]);

// Communication — Appointment Notes
$router->get('/api/appointments/{id}/notes', 'NoteController@index', [TenantMiddleware::class, AuthMiddleware::class, [RoleMiddleware::class, [Roles::PROVIDER, Roles::NURSE]]]);
$router->post('/api/appointments/{id}/notes', 'NoteController@store', [TenantMiddleware::class, AuthMiddleware::class, CsrfMiddleware::class, [RoleMiddleware::class, [Roles::PROVIDER, Roles::NURSE]]]);
$router->put('/api/notes/{id}', 'NoteController@update', [TenantMiddleware::class, AuthMiddleware::class, CsrfMiddleware::class, [RoleMiddleware::class, [Roles::PROVIDER, Roles::NURSE]]]);
$router->delete('/api/notes/{id}', 'NoteController@destroy', [TenantMiddleware::class, AuthMiddleware::class, CsrfMiddleware::class, [RoleMiddleware::class, [Roles::PROVIDER, Roles::NURSE]]]);

// Calendar API
$router->get('/api/calendar', 'CalendarController@index', [TenantMiddleware::class, AuthMiddleware::class, [RoleMiddleware::class, [Roles::ADMIN, Roles::RECEPTIONIST, Roles::NURSE, Roles::PROVIDER]]]);
$router->get('/api/calendar/appointments/{id}/tooltip', 'CalendarController@tooltip', [TenantMiddleware::class, AuthMiddleware::class, [RoleMiddleware::class, [Roles::ADMIN, Roles::RECEPTIONIST, Roles::NURSE, Roles::PROVIDER]]]);

// Billing management
$router->get('/api/invoices', 'BillingController@index', [TenantMiddleware::class, AuthMiddleware::class, [RoleMiddleware::class, [Roles::ADMIN, Roles::PROVIDER]]]);
$router->get('/api/invoices/my', 'BillingController@myInvoices', [TenantMiddleware::class, AuthMiddleware::class, [RoleMiddleware::class, [Roles::PATIENT]]]);
$router->get('/api/invoices/pending-summary', 'BillingController@pendingSummary', [TenantMiddleware::class, AuthMiddleware::class, [RoleMiddleware::class, [Roles::ADMIN, Roles::PROVIDER]]]);
$router->get('/api/invoices/paid-summary', 'BillingController@paidSummary', [TenantMiddleware::class, AuthMiddleware::class, [RoleMiddleware::class, [Roles::ADMIN, Roles::PROVIDER]]]);
$router->post('/api/invoices', 'BillingController@store', [TenantMiddleware::class, AuthMiddleware::class, CsrfMiddleware::class, [RoleMiddleware::class, [Roles::ADMIN, Roles::PROVIDER]]]);
$router->put('/api/invoices/{id}', 'BillingController@update', [TenantMiddleware::class, AuthMiddleware::class, CsrfMiddleware::class, [RoleMiddleware::class, [Roles::ADMIN, Roles::PROVIDER]]]);

// Staff management
$router->get('/api/staff', 'DoctorController@index', [TenantMiddleware::class, AuthMiddleware::class, [RoleMiddleware::class, [Roles::ADMIN]]]);
$router->get('/api/staff/{id}', 'DoctorController@show', [TenantMiddleware::class, AuthMiddleware::class, [RoleMiddleware::class, [Roles::ADMIN]]]);
$router->post('/api/staff', 'DoctorController@store', [TenantMiddleware::class, AuthMiddleware::class, CsrfMiddleware::class, [RoleMiddleware::class, [Roles::ADMIN]]]);
$router->put('/api/staff/{id}', 'DoctorController@update', [TenantMiddleware::class, AuthMiddleware::class, CsrfMiddleware::class, [RoleMiddleware::class, [Roles::ADMIN]]]);
$router->patch('/api/staff/{id}/activate', 'DoctorController@activate', [TenantMiddleware::class, AuthMiddleware::class, CsrfMiddleware::class, [RoleMiddleware::class, [Roles::ADMIN]]]);
$router->patch('/api/staff/{id}/deactivate', 'DoctorController@deactivate', [TenantMiddleware::class, AuthMiddleware::class, CsrfMiddleware::class, [RoleMiddleware::class, [Roles::ADMIN]]]);
$router->delete('/api/staff/{id}', 'DoctorController@destroy', [TenantMiddleware::class, AuthMiddleware::class, CsrfMiddleware::class, [RoleMiddleware::class, [Roles::ADMIN]]]);

// User management
// $router->get('/api/users', 'UserController@index', [TenantMiddleware::class, AuthMiddleware::class, [RoleMiddleware::class, [Roles::ADMIN]]]);
$router->get('/api/users/{id}', 'UserController@show', [TenantMiddleware::class, AuthMiddleware::class, [RoleMiddleware::class, [Roles::ADMIN]]]);
$router->get('/api/users', 'UserController@index', [TenantMiddleware::class, AuthMiddleware::class, [RoleMiddleware::class, [Roles::ADMIN, Roles::PROVIDER, Roles::NURSE]]]);
$router->post('/api/users', 'UserController@store', [TenantMiddleware::class, AuthMiddleware::class, CsrfMiddleware::class, [RoleMiddleware::class, [Roles::ADMIN]]]);
$router->put('/api/users/{id}', 'UserController@update', [TenantMiddleware::class, AuthMiddleware::class, CsrfMiddleware::class, [RoleMiddleware::class, [Roles::ADMIN]]]);
$router->patch('/api/users/{id}/activate', 'UserController@activate', [TenantMiddleware::class, AuthMiddleware::class, CsrfMiddleware::class, [RoleMiddleware::class, [Roles::ADMIN]]]);
$router->patch('/api/users/{id}/deactivate', 'UserController@deactivate', [TenantMiddleware::class, AuthMiddleware::class, CsrfMiddleware::class, [RoleMiddleware::class, [Roles::ADMIN]]]);
$router->delete('/api/users/{id}', 'UserController@destroy', [TenantMiddleware::class, AuthMiddleware::class, CsrfMiddleware::class, [RoleMiddleware::class, [Roles::ADMIN]]]);

// Prescription management
$router->get('/api/prescriptions', 'PrescriptionController@index', [TenantMiddleware::class, AuthMiddleware::class, [RoleMiddleware::class, [Roles::PROVIDER, Roles::PHARMACIST, Roles::PATIENT]]]);
$router->post('/api/prescriptions', 'PrescriptionController@store', [TenantMiddleware::class, AuthMiddleware::class, CsrfMiddleware::class, [RoleMiddleware::class, [Roles::PROVIDER]]]);
$router->get('/api/prescriptions/{id}', 'PrescriptionController@show', [TenantMiddleware::class, AuthMiddleware::class, [RoleMiddleware::class, [Roles::PROVIDER, Roles::PHARMACIST, Roles::PATIENT]]]);
$router->put('/api/prescriptions/{id}', 'PrescriptionController@update', [TenantMiddleware::class, AuthMiddleware::class, CsrfMiddleware::class, [RoleMiddleware::class, [Roles::PROVIDER]]]);
$router->put('/api/prescriptions/{id}/status', 'PrescriptionController@updateStatus', [TenantMiddleware::class, AuthMiddleware::class, CsrfMiddleware::class, [RoleMiddleware::class, [Roles::ADMIN, Roles::PROVIDER]]]);

// Prescription items management
$router->post('/api/prescriptions/{id}/items', 'PrescriptionController@addItem', [TenantMiddleware::class, AuthMiddleware::class, CsrfMiddleware::class, [RoleMiddleware::class, [Roles::PROVIDER]]]);
$router->put('/api/prescriptions/{id}/items/{item_id}', 'PrescriptionController@updateItem', [TenantMiddleware::class, AuthMiddleware::class, CsrfMiddleware::class, [RoleMiddleware::class, [Roles::PROVIDER]]]);
$router->delete('/api/prescriptions/{id}/items/{item_id}', 'PrescriptionController@deleteItem', [TenantMiddleware::class, AuthMiddleware::class, CsrfMiddleware::class, [RoleMiddleware::class, [Roles::PROVIDER]]]);

// Pharmacy operations (pharmacist APIs)
$router->post('/api/prescriptions/{id}/verify', 'PrescriptionController@verify', [TenantMiddleware::class, AuthMiddleware::class, CsrfMiddleware::class, [RoleMiddleware::class, [Roles::PHARMACIST]]]);
$router->post('/api/prescriptions/{id}/dispense', 'PrescriptionController@dispense', [TenantMiddleware::class, AuthMiddleware::class, CsrfMiddleware::class, [RoleMiddleware::class, [Roles::PHARMACIST]]]);


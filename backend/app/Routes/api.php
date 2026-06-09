<?php

// api.php

use App\Config\Roles;
use App\Middleware\CsrfMiddleware;
use App\Middleware\AuthMiddleware;
use App\Middleware\RoleMiddleware;

/** @var \App\Core\Router $router */

// Public routes
$router->post('/api/register', 'AuthController@register');
$router->post('/api/login', 'AuthController@login');
$router->post('/api/refresh', 'AuthController@refresh');

// Protected auth routes
$router->post('/api/logout', 'AuthController@logout', [AuthMiddleware::class, CsrfMiddleware::class]);
$router->get('/api/profile', 'AuthController@profile', [AuthMiddleware::class]);
$router->post('/api/change-password', 'AuthController@changePassword', [AuthMiddleware::class, CsrfMiddleware::class]);
$router->get('/api/dashboard', 'DashboardController@index', [AuthMiddleware::class, [RoleMiddleware::class, [Roles::ADMIN, Roles::PROVIDER]]]);

// Patient management
$router->get('/api/patients', 'PatientController@index', [AuthMiddleware::class, [RoleMiddleware::class, [Roles::PROVIDER, Roles::NURSE]]]);
$router->post('/api/patients', 'PatientController@store', [AuthMiddleware::class, CsrfMiddleware::class, [RoleMiddleware::class, [Roles::PROVIDER, Roles::NURSE]]]);
$router->get('/api/patients/{id}', 'PatientController@show', [AuthMiddleware::class, [RoleMiddleware::class, [Roles::PROVIDER, Roles::NURSE]]]);
$router->put('/api/patients/{id}', 'PatientController@update', [AuthMiddleware::class, CsrfMiddleware::class, [RoleMiddleware::class, [Roles::PROVIDER, Roles::NURSE]]]);
$router->delete('/api/patients/{id}', 'PatientController@destroy', [AuthMiddleware::class, CsrfMiddleware::class, [RoleMiddleware::class, [Roles::PROVIDER, Roles::NURSE]]]);

// Appointment management
$router->get('/api/appointments', 'AppointmentController@index', [AuthMiddleware::class, [RoleMiddleware::class, [Roles::PROVIDER, Roles::NURSE, Roles::PATIENT]]]);
$router->post('/api/appointments', 'AppointmentController@store', [AuthMiddleware::class, CsrfMiddleware::class, [RoleMiddleware::class, [Roles::PROVIDER, Roles::NURSE, Roles::PATIENT]]]);
$router->get('/api/appointments/{id}', 'AppointmentController@show', [AuthMiddleware::class, [RoleMiddleware::class, [Roles::PROVIDER, Roles::NURSE, Roles::PATIENT]]]);
$router->put('/api/appointments/{id}', 'AppointmentController@update', [AuthMiddleware::class, CsrfMiddleware::class, [RoleMiddleware::class, [Roles::PROVIDER, Roles::NURSE, Roles::PATIENT]]]);
$router->delete('/api/appointments/{id}', 'AppointmentController@destroy', [AuthMiddleware::class, CsrfMiddleware::class, [RoleMiddleware::class, [Roles::PROVIDER, Roles::NURSE, Roles::PATIENT]]]);

// Communication — Appointment Notes
$router->get('/api/appointments/{id}/notes', 'NoteController@index', [AuthMiddleware::class, [RoleMiddleware::class, [Roles::PROVIDER, Roles::NURSE]]]);
$router->post('/api/appointments/{id}/notes', 'NoteController@store', [AuthMiddleware::class, CsrfMiddleware::class, [RoleMiddleware::class, [Roles::PROVIDER, Roles::NURSE]]]);
$router->put('/api/notes/{id}', 'NoteController@update', [AuthMiddleware::class, CsrfMiddleware::class, [RoleMiddleware::class, [Roles::PROVIDER, Roles::NURSE]]]);
$router->delete('/api/notes/{id}', 'NoteController@destroy', [AuthMiddleware::class, CsrfMiddleware::class, [RoleMiddleware::class, [Roles::PROVIDER, Roles::NURSE]]]);

// Calendar API
$router->get('/api/calendar', 'CalendarController@index', [AuthMiddleware::class, [RoleMiddleware::class, [Roles::ADMIN, Roles::RECEPTIONIST, Roles::NURSE, Roles::PROVIDER]]]);
$router->get('/api/calendar/appointments/{id}/tooltip', 'CalendarController@tooltip', [AuthMiddleware::class, [RoleMiddleware::class, [Roles::ADMIN, Roles::RECEPTIONIST, Roles::NURSE, Roles::PROVIDER]]]);

// Billing management
$router->get('/api/invoices', 'BillingController@index', [AuthMiddleware::class, [RoleMiddleware::class, [Roles::ADMIN, Roles::PROVIDER, Roles::PATIENT]]]);
$router->get('/api/invoices/my', 'BillingController@myInvoices', [AuthMiddleware::class, [RoleMiddleware::class, [Roles::PATIENT]]]);
$router->get('/api/invoices/pending-summary', 'BillingController@pendingSummary', [AuthMiddleware::class, [RoleMiddleware::class, [Roles::ADMIN, Roles::PROVIDER]]]);
$router->get('/api/invoices/paid-summary', 'BillingController@paidSummary', [AuthMiddleware::class, [RoleMiddleware::class, [Roles::ADMIN, Roles::PROVIDER]]]);
$router->post('/api/invoices', 'BillingController@store', [AuthMiddleware::class, CsrfMiddleware::class, [RoleMiddleware::class, [Roles::ADMIN, Roles::PROVIDER]]]);
$router->put('/api/invoices/{id}', 'BillingController@update', [AuthMiddleware::class, CsrfMiddleware::class, [RoleMiddleware::class, [Roles::ADMIN, Roles::PROVIDER]]]);

// Staff management
$router->get('/api/staff', 'DoctorController@index', [AuthMiddleware::class, [RoleMiddleware::class, [Roles::ADMIN]]]);
$router->get('/api/staff/{id}', 'DoctorController@show', [AuthMiddleware::class, [RoleMiddleware::class, [Roles::ADMIN]]]);
$router->post('/api/staff', 'DoctorController@store', [AuthMiddleware::class, CsrfMiddleware::class, [RoleMiddleware::class, [Roles::ADMIN]]]);
$router->put('/api/staff/{id}', 'DoctorController@update', [AuthMiddleware::class, CsrfMiddleware::class, [RoleMiddleware::class, [Roles::ADMIN]]]);
$router->patch('/api/staff/{id}/activate', 'DoctorController@activate', [AuthMiddleware::class, CsrfMiddleware::class, [RoleMiddleware::class, [Roles::ADMIN]]]);
$router->patch('/api/staff/{id}/deactivate', 'DoctorController@deactivate', [AuthMiddleware::class, CsrfMiddleware::class, [RoleMiddleware::class, [Roles::ADMIN]]]);
$router->delete('/api/staff/{id}', 'DoctorController@destroy', [AuthMiddleware::class, CsrfMiddleware::class, [RoleMiddleware::class, [Roles::ADMIN]]]);

// User management
$router->get('/api/users', 'UserController@index', [AuthMiddleware::class, [RoleMiddleware::class, [Roles::ADMIN]]]);
$router->get('/api/users/{id}', 'UserController@show', [AuthMiddleware::class, [RoleMiddleware::class, [Roles::ADMIN]]]);
$router->post('/api/users', 'UserController@store', [AuthMiddleware::class, CsrfMiddleware::class, [RoleMiddleware::class, [Roles::ADMIN]]]);
$router->put('/api/users/{id}', 'UserController@update', [AuthMiddleware::class, CsrfMiddleware::class, [RoleMiddleware::class, [Roles::ADMIN]]]);
$router->delete('/api/users/{id}', 'UserController@destroy', [AuthMiddleware::class, CsrfMiddleware::class, [RoleMiddleware::class, [Roles::ADMIN]]]);

// Prescription management
$router->get('/api/prescriptions', 'PrescriptionController@index', [AuthMiddleware::class, [RoleMiddleware::class, [Roles::PROVIDER, Roles::PHARMACIST, Roles::PATIENT]]]);
$router->post('/api/prescriptions', 'PrescriptionController@store', [AuthMiddleware::class, CsrfMiddleware::class, [RoleMiddleware::class, [Roles::PROVIDER]]]);
$router->get('/api/prescriptions/{id}', 'PrescriptionController@show', [AuthMiddleware::class, [RoleMiddleware::class, [Roles::PROVIDER, Roles::PHARMACIST, Roles::PATIENT]]]);
$router->put('/api/prescriptions/{id}', 'PrescriptionController@update', [AuthMiddleware::class, CsrfMiddleware::class, [RoleMiddleware::class, [Roles::PROVIDER]]]);
$router->put('/api/prescriptions/{id}/status', 'PrescriptionController@updateStatus', [AuthMiddleware::class, CsrfMiddleware::class, [RoleMiddleware::class, [Roles::ADMIN, Roles::PROVIDER]]]);

// Prescription items management
$router->post('/api/prescriptions/{id}/items', 'PrescriptionController@addItem', [AuthMiddleware::class, CsrfMiddleware::class, [RoleMiddleware::class, [Roles::PROVIDER]]]);
$router->put('/api/prescriptions/{id}/items/{item_id}', 'PrescriptionController@updateItem', [AuthMiddleware::class, CsrfMiddleware::class, [RoleMiddleware::class, [Roles::PROVIDER]]]);
$router->delete('/api/prescriptions/{id}/items/{item_id}', 'PrescriptionController@deleteItem', [AuthMiddleware::class, CsrfMiddleware::class, [RoleMiddleware::class, [Roles::PROVIDER]]]);

// Pharmacy operations (pharmacist APIs)
$router->post('/api/prescriptions/{id}/verify', 'PrescriptionController@verify', [AuthMiddleware::class, CsrfMiddleware::class, [RoleMiddleware::class, [Roles::PHARMACIST]]]);
$router->post('/api/prescriptions/{id}/dispense', 'PrescriptionController@dispense', [AuthMiddleware::class, CsrfMiddleware::class, [RoleMiddleware::class, [Roles::PHARMACIST]]]);



# PHP-MVP API Development Workflow

## Base URL

```
http://localhost/PHP-MVP/backend/public
```

---

# Authentication Flow

> The API uses JWT access tokens passed via the `Authorization: Bearer <token>` header and HttpOnly refresh tokens stored in cookies. CSRF tokens are still required for state-changing requests.

## Step 1: Get CSRF Token

Request

```
GET /api/csrf-token
```

Example

```
GET http://localhost/PHP-MVP/backend/public/api/csrf-token
```

Response

```
{
  "csrf_token": "generated_token_here"
}
```

Save:

- `csrf_token`
- `PHPSESSID` cookie (Postman stores cookies automatically)

---

## Step 2: Register User

Request

```
POST /api/register
```

Headers

```
Content-Type: application/json
X-CSRF-TOKEN: csrf_token
```

Body

```
{
  "name": "Admin User",
  "email": "admin@test.com",
  "password": "password123",
  "role_id": 1,
  "tenant_id": 1
}
```

Response

```
{
  "message": "Registration successful",
  "user_id": 1
}
```

---

## Step 3: Login

Request

```
POST /api/login
```

Headers

```
Content-Type: application/json
X-CSRF-TOKEN: csrf_token
```

Body

```
{
  "email": "admin@test.com",
  "password": "password123"
}
```

Response

```
{
  "message": "Login successful",
  "user": {
    "id": 1,
    "name": "Admin User",
    "email": "admin@test.com",
    "role_id": 1,
    "tenant_id": 1
  },
  "access_token": "jwt_token_here"
}
```

Save:

- `access_token` (use this for `Authorization` header)

Postman automatically stores:

- `PHPSESSID`
- `refresh_token` (HttpOnly cookie)

---

# Protected Routes

All protected routes require the Authorization header:

```
Authorization: Bearer ACCESS_TOKEN
```

Example

```
Authorization: Bearer eyJhbGciOiJIUzI1Ni...
```

---

# Profile API

Request

```
GET /api/profile
```

Headers

```
Authorization: Bearer ACCESS_TOKEN
```

Response

```
{
  "message": "Profile retrieved",
  "user": {
    ...
  }
}
```

---

# Refresh Token Flow

When access token expires:

Request

```
POST /api/refresh
```

Headers

```
X-CSRF-TOKEN: csrf_token
```

No body required — the server reads the refresh token from the HttpOnly cookie.

Response

```
{
  "message": "Token refreshed",
  "access_token": "new_access_token"
}
```

Replace your old access token with the new one returned.

---

# Logout

Request

```
POST /api/logout
```

Headers

```
Authorization: Bearer ACCESS_TOKEN
X-CSRF-TOKEN: csrf_token
```

Response

```
{
  "message": "Logged out successfully"
}
```

This will:

- Revoke refresh tokens
- Clear the refresh token cookie
- Invalidate any authentication-related session values

---

# Role IDs

Use the following numeric role IDs when creating or testing users:

- Admin: 1
- Provider: 2
- Nurse: 3
- Patient: 4
- Pharmacist: 5
- Doctor: 6
- Receptionist: 7

---

# Patient Module API Rules

Allowed Roles:

- Provider
- Nurse

Authorization Required: YES

Example Header

```
Authorization: Bearer ACCESS_TOKEN
```

Create Patient

```
POST /api/patients
```

Body

```
{
  "name": "John Doe",
  "dob": "1995-10-20",
  "gender": "Male",
  "phone": "9999999999",
  "email": "john@test.com",
  "address": "123 Main St, City",
  "blood_group": "O+",
  "medical_history": "No known allergies",
  "emergency_contact": "8888888888"
}
```

Sensitive fields (PHI) must be encrypted before storing in the database and decrypted only when returning responses. Typical PHI fields include:

- Name
- DOB
- Phone
- Email
- Medical history
- Address

---

# Appointment Module API Rules

Allowed Roles:

- Provider
- Nurse
- Patient

Authorization Required: YES

Create Appointment

```
POST /api/appointments
```

Body

```
{
  "patient_id": 1,
  "provider_id": 2,
  "appointment_date": "2026-06-10",
  "appointment_time": "10:00:00"
}
```

Must validate on the server:

- Provider exists
- Patient exists
- No overlapping appointment for the same provider

---

# Calendar Module API Rules

Allowed Roles:

- Admin
- Doctor
- Nurse
- Receptionist

Authorization Required: YES

Get Calendar Appointments (Single Day)

```
GET /api/calendar?date=2026-06-10
```

Get Calendar Appointments (Date Range)

```
GET /api/calendar?start_date=2026-06-01&end_date=2026-06-30
```

Get Appointment Tooltip Details

```
GET /api/calendar/appointments/{id}/tooltip
```

---

# RBAC Testing

Admin Route

```
GET /api/staff
```

Allowed: Admin

Forbidden: Provider, Nurse, Patient, Pharmacist, Receptionist

Expected response when unauthorized by role:

```
{
  "error": "Forbidden - insufficient permissions"
}
```

Status Code: `403 Forbidden`

---

# Development Rules

1. Always create APIs following: Controller → Service → Repository → Database.
2. Never write raw SQL inside Controllers.
3. Always validate JWT, Role, and Tenant access for protected routes.
4. Encrypt PHI before storing (Name, DOB, Phone, Email, Medical history, Address).
5. Decrypt PHI only when returning responses.
6. Use prepared statements for all DB access.
7. Never trust client input; validate and sanitize everything.
8. Test every API in Postman before pushing code.
9. Create a feature branch for development.
10. Raise a Pull Request to `main` after testing.

---

If you'd like, I can also update example Postman collections or add a short local testing checklist.
```json
{
  "message": "Token refreshed",
  "tokens": {
    "access_token": "eyJhbGc...",
    "refresh_token": "eyJhbGc..."
  }
}
```

### Authenticated endpoints

#### GET /api/profile
Returns the current user profile. Requires authentication via JWT token in session.

**Response:**
```json
{
  "message": "Profile retrieved",
  "user": {
    "id": 1,
    "name": "Admin User",
    "email": "admin@test.com",
    "role_id": 1,
    "tenant_id": 1
  }
}
```

#### POST /api/change-password
Allows an authenticated user to change their password. Requires authentication via JWT token.

**Request:**
```json
{
  "current_password": "old_password123",
  "new_password": "new_secure_password",
  "confirm_password": "new_secure_password"
}
```

**Response:**
```json
{
  "success": true,
  "message": "Password changed successfully"
}
```

#### POST /api/logout
Invalidates refresh tokens and logs out the user.

**Response:**
```json
{
  "message": "Logged out successfully"
}
```

#### GET /api/dashboard
Returns tenant-level dashboard metrics (patients, appointments, invoices).

**Response:**
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

### Patient management

- `GET /api/patients` - List all patients
- `POST /api/patients` - Add a new patient
  - **Request:** `{ "name": "John Doe", "dob": "1995-10-20", "gender": "Male", "phone": "9999999999", "email": "john@test.com", "address": "123 Main St, City", "blood_group": "O+", "medical_history": "No known allergies", "emergency_contact": "8888888888" }`
- `GET /api/patients/{id}` - Get patient details
- `PUT /api/patients/{id}` - Update patient information
  - **Request:** `{ "name": "John Doe", "phone": "8888888888", "address": "456 New St" }`
- `DELETE /api/patients/{id}` - Soft delete a patient

**Note:** PHI fields (name, dob, gender, phone, email, etc.) are encrypted in the database and automatically decrypted in responses.

### Appointment management

- `GET /api/appointments` - List all appointments
- `POST /api/appointments` - Create a new appointment
  - **Request:** `{ "patient_id": 1, "provider_id": 2, "appointment_date": "2026-06-10", "appointment_time": "10:00:00" }`
- `GET /api/appointments/{id}` - Get appointment details
- `PUT /api/appointments/{id}` - Update appointment
  - **Request:** `{ "status": "completed", "notes": "updated notes..." }`
- `DELETE /api/appointments/{id}` - Cancel an appointment

**Note:** `notes` field is encrypted in the database.

### Calendar management

- `GET /api/calendar` - Get appointments for a calendar view
  - **Query Params:** `?date=YYYY-MM-DD` OR `?start_date=YYYY-MM-DD&end_date=YYYY-MM-DD`
- `GET /api/calendar/appointments/{id}/tooltip` - Get specific details for rendering an appointment tooltip

### Billing management

- `GET /api/invoices` - List all invoices
- `POST /api/invoices` - Generate a new invoice
  - **Request:** `{ "patient_id": 1, "amount": 150.00, "description": "Consultation" }`
- `PUT /api/invoices/{id}` - Update invoice status
  - **Request:** `{ "status": "paid" }`

### Staff management

- `GET /api/staff` - List all staff members
- `POST /api/staff` - Create new staff member
  - **Request:** `{ "name": "Dr. Smith", "email": "doctor@test.com", "password": "securepass", "role_id": 2 }`
  - **Requires:** Admin role
- `PUT /api/staff/{id}` - Update staff information
  - **Request:** `{ "name": "Dr. Smith", "email": "doctor@test.com" }`
- `DELETE /api/staff/{id}` - Deactivate staff member

### User management

- `GET /api/users` - List users (supports pagination & filtering)
  - **Query Params:** `?page=1&limit=10&name=John&email=john@test.com`
  - **Requires:** Admin role
- `GET /api/users/{id}` - Get a specific user details
  - **Requires:** Admin role
- `POST /api/users` - Create a new user
  - **Request:** `{ "name": "Jane Doe", "email": "jane@test.com", "password": "securepass", "role": 3 }`
  - **Requires:** Admin role
- `PUT /api/users/{id}` - Update a user's details
  - **Request:** `{ "name": "Jane Doe", "email": "jane@test.com", "role": 3 }`
  - **Requires:** Admin role
- `DELETE /api/users/{id}` - Soft delete a user
  - **Requires:** Admin role

## Notes

- **JWT Authentication**: Access tokens are short-lived and stored in PHP sessions after login.
- **Refresh Tokens**: Stored securely in the database and can be used to obtain new access tokens.
- **Tenant Isolation**: Enforced by tenant ID in JWT claims and all repository queries.
- **PHI Encryption**: Protected Health Information (medical_data, appointment notes, etc.) is automatically encrypted at the database level using AES-256-CBC and decrypted when retrieved.
- **Password Security**: All passwords are hashed using `password_hash()` (PHP's default bcrypt).
- **Plain JSON APIs**: All endpoints accept and return plain JSON - encryption is handled transparently for PHI fields at the database layer.
- **AES Configuration**: Uses `AES-256-CBC` cipher with the key configured in `.env` as `AES_KEY`.

## Testing with Postman

### 1. Register a new user

**Method:** POST  
**URL:** `http://localhost/PHP-MVP/backend/public/api/register`  
**Headers:** 
- `Content-Type: application/json`
- `X-CSRF-TOKEN: <your_csrf_token>`
**Body:**
```json
{
  "name": "Admin User",
  "email": "admin@test.com",
  "password": "password123",
  "tenant_id": 1,
  "role_id": 1
}
```

### 2. Login

**Method:** POST  
**URL:** `http://localhost/PHP-MVP/backend/public/api/login`  
**Headers:** 
- `Content-Type: application/json`
- `X-CSRF-TOKEN: <your_csrf_token>`
**Body:**
```json
{
  "email": "admin@test.com",
  "password": "password123"
}
```

Save the returned `access_token` from the response for authenticated requests.

### 3. Get Dashboard (Authenticated)

**Method:** GET  
**URL:** `http://localhost/PHP-MVP/backend/public/api/dashboard`  
**Headers:** 
- `Content-Type: application/json`
- `Authorization: Bearer <your_access_token>`

### 4. Add a Patient

**Method:** POST  
**URL:** `http://localhost/PHP-MVP/backend/public/api/patients`  
**Headers:** 
- `Content-Type: application/json`
- `Authorization: Bearer <your_access_token>`  
- `X-CSRF-TOKEN: <your_csrf_token>`
**Body:**
```json
{
  "name": "John Doe",
  "dob": "1995-10-20",
  "gender": "Male",
  "phone": "9999999999",
  "email": "john@test.com",
  "address": "123 Main St, City",
  "blood_group": "O+",
  "medical_history": "No known allergies",
  "emergency_contact": "8888888888"
}
```

### 5. Create an Appointment

**Method:** POST  
**URL:** `http://localhost/PHP-MVP/backend/public/api/appointments`  
**Headers:** 
- `Content-Type: application/json`
- `Authorization: Bearer <your_access_token>`  
- `X-CSRF-TOKEN: <your_csrf_token>`
**Body:**
```json
{
  "patient_id": 1,
  "provider_id": 2,
  "appointment_date": "2026-06-10",
  "appointment_time": "10:00:00"
}
```

### 6. Get Calendar Events

**Method:** GET  
**URL:** `http://localhost/PHP-MVP/backend/public/api/calendar?date=2026-06-10`  
**Headers:** 
- `Content-Type: application/json`
- `Authorization: Bearer <your_access_token>`  

## Development Workflow

- Work in feature branches.
- Create Pull Requests for review.
- Do not commit directly to `main`.

### Example git commands

```bash
git checkout main
git pull origin main
git checkout -b feature/auth
# ... make changes ...
git add .
git commit -m "Add login API"
git push origin feature/auth
```

# ONESALEZ Service CRM — API Usage Guide

This document covers every API currently implemented in the PHP backend.

## 1. Base URL

Production:

```text
https://service.onesalez.com/api/v1
```

Local Docker environment:

```text
http://localhost:8080/api/v1
```

The examples below use an environment variable to keep commands short:

```bash
BASE_URL="https://service.onesalez.com/api/v1"
```

## 2. Request and response conventions

Send JSON request bodies with:

```http
Content-Type: application/json
```

Protected APIs require the JWT access token returned by login:

```http
Authorization: Bearer YOUR_ACCESS_TOKEN
```

Example shell variable:

```bash
ACCESS_TOKEN="paste-access-token-here"
```

Successful response format:

```json
{
  "success": true,
  "data": {},
  "error": null,
  "timestamp": "2026-06-19T12:00:00+00:00"
}
```

Error response format:

```json
{
  "success": false,
  "data": null,
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "Validation failed.",
    "details": {
      "email": ["email must be a valid email address."]
    }
  },
  "timestamp": "2026-06-19T12:00:00+00:00"
}
```

## 3. Health check

### Check API availability

No authentication is required.

```bash
curl "$BASE_URL/health"
```

## 4. Authentication APIs

Client contacts and ONESALEZ employees authenticate separately. Use `client` for a client contact and `employee` for an ONESALEZ employee.

### Login

```bash
curl -X POST "$BASE_URL/auth/login" \
  -H "Content-Type: application/json" \
  --cookie-jar cookies.txt \
  -d '{
    "email": "employee@example.com",
    "password": "a-secure-password",
    "realm": "employee"
  }'
```

The response contains:

- `access_token`: Short-lived JWT used on protected requests.
- Refresh session: Stored only in a Secure, HttpOnly cookie and never exposed to browser JavaScript.
- `expires_in`: Access-token lifetime in seconds.
- `actor`: Authenticated user information, roles, and permissions.

### Refresh tokens

Each successful refresh reads the HttpOnly cookie, revokes the old refresh token, rotates the cookie, and returns a new access token.

```bash
curl -X POST "$BASE_URL/auth/refresh" \
  -H "Content-Type: application/json" \
  --cookie-jar cookies.txt \
  --cookie cookies.txt
```

### Logout

Logout revokes the refresh token held in the HttpOnly cookie and clears the cookie.

```bash
curl -X POST "$BASE_URL/auth/logout" \
  -H "Content-Type: application/json" \
  --cookie-jar cookies.txt \
  --cookie cookies.txt
```

### Request a password-reset email

The API always returns the same response so outsiders cannot discover registered email addresses.

```bash
curl -X POST "$BASE_URL/auth/forgot-password" \
  -H "Content-Type: application/json" \
  -d '{
    "email": "person@example.com",
    "realm": "client"
  }'
```

### Reset a password

Passwords must contain at least 12 characters.

```bash
curl -X POST "$BASE_URL/auth/reset-password" \
  -H "Content-Type: application/json" \
  -d '{
    "token": "TOKEN_FROM_RESET_EMAIL",
    "password": "a-new-secure-password",
    "realm": "client"
  }'
```

### Register an ONESALEZ employee

The caller must be an employee with `employees.manage`, normally a `SYSTEM_ADMIN`.

```bash
curl -X POST "$BASE_URL/auth/register" \
  -H "Authorization: Bearer $ACCESS_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "employee_code": "EMP002",
    "full_name": "Service Employee",
    "official_email": "service.employee@example.com",
    "mobile_number": "9876543210",
    "designation": "Support Executive",
    "department": "Service",
    "joining_date": "2026-06-19",
    "password": "a-temporary-secure-password",
    "roles": ["SERVICE_EMPLOYEE"]
  }'
```

Supported employee roles:

- `SERVICE_EMPLOYEE`
- `SERVICE_ADMIN`
- `SYSTEM_ADMIN`

### Register a client contact

The caller must be an authenticated client contact with the `CLIENT_ADMIN` role. The new contact is automatically created under the caller's client.

```bash
curl -X POST "$BASE_URL/auth/register" \
  -H "Authorization: Bearer $ACCESS_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "contact_role_id": 2,
    "full_name": "Client User",
    "designation": "Billing Operator",
    "email": "operator@client.example",
    "mobile_number": "9876543210",
    "has_all_locations": true,
    "is_primary_contact": false,
    "password": "a-temporary-secure-password"
  }'
```

## 5. Lead APIs

Lead APIs require the `leads.manage` permission.

### List, search, filter, sort, and paginate leads

```bash
curl "$BASE_URL/leads?page=1&limit=20&status=NEW&search=trading&sort=created_at&order=desc" \
  -H "Authorization: Bearer $ACCESS_TOKEN"
```

Supported statuses: `NEW`, `CONTACTED`, `QUALIFIED`, `WON`, and `LOST`.

Supported sort fields: `business_name`, `status`, and `created_at`.

### Get one lead

```bash
curl "$BASE_URL/leads/1" \
  -H "Authorization: Bearer $ACCESS_TOKEN"
```

### Create a lead

```bash
curl -X POST "$BASE_URL/leads" \
  -H "Authorization: Bearer $ACCESS_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "business_name": "Example Trading House",
    "contact_name": "Example Owner",
    "email": "owner@example.com",
    "phone": "9876543210",
    "source": "Website",
    "status": "NEW",
    "assigned_employee_id": 2,
    "notes": "Interested in retail software."
  }'
```

### Update a lead

Only supplied fields are changed.

```bash
curl -X PUT "$BASE_URL/leads/1" \
  -H "Authorization: Bearer $ACCESS_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "status": "QUALIFIED",
    "notes": "Product demonstration completed."
  }'
```

### Bulk-update lead status

```bash
curl -X POST "$BASE_URL/leads/bulk-status" \
  -H "Authorization: Bearer $ACCESS_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "ids": [1, 2, 3],
    "status": "CONTACTED"
  }'
```

### Soft-delete a lead

```bash
curl -X DELETE "$BASE_URL/leads/1" \
  -H "Authorization: Bearer $ACCESS_TOKEN"
```

## 6. Customer APIs

In API terminology, a `customer` is a record from the `clients` table.

### List customers

Employees see permitted clients. A client contact sees only their own client.

```bash
curl "$BASE_URL/customers?page=1&limit=20&search=trading" \
  -H "Authorization: Bearer $ACCESS_TOKEN"
```

### Get customer, locations, and contacts

```bash
curl "$BASE_URL/customers/1" \
  -H "Authorization: Bearer $ACCESS_TOKEN"
```

### Create a customer

Requires `clients.manage`.

```bash
curl -X POST "$BASE_URL/customers" \
  -H "Authorization: Bearer $ACCESS_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "client_code": "CLI001",
    "legal_name": "Example Trading Private Limited",
    "display_name": "Example Trading",
    "gstin": "27ABCDE1234F1Z5",
    "pan": "ABCDE1234F",
    "primary_email": "office@example.com",
    "primary_phone": "9876543210",
    "website_url": "https://example.com",
    "notes": "Initial customer record."
  }'
```

### Onboard a complete client

Requires `clients.manage`. This atomic operation creates the client, its primary location,
the initial client-side administrator, and an active portal login. If any step fails, no
records are saved.

```bash
curl -X POST "$BASE_URL/customers/onboard" \
  -H "Authorization: Bearer $ACCESS_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "client": {
      "client_code": "CLI001",
      "legal_name": "Example Trading Private Limited",
      "display_name": "Example Trading",
      "gstin": "27ABCDE1234F1Z5"
    },
    "location": {
      "location_code": "HO01",
      "location_name": "Head Office",
      "location_type": "HEAD_OFFICE",
      "address_line_1": "12 Business Park",
      "city": "Mumbai",
      "state_name": "Maharashtra",
      "postal_code": "400001"
    },
    "administrator": {
      "full_name": "Client Administrator",
      "designation": "IT Manager",
      "email": "admin@example.com",
      "mobile_number": "9876543210",
      "password": "Temporary@123"
    }
  }'
```

### Update a customer

Requires `clients.manage`.

```bash
curl -X PUT "$BASE_URL/customers/1" \
  -H "Authorization: Bearer $ACCESS_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "display_name": "Example Retail and Wholesale",
    "primary_phone": "9000000000"
  }'
```

### View customer service history

```bash
curl "$BASE_URL/customers/1/service-history?page=1&limit=20" \
  -H "Authorization: Bearer $ACCESS_TOKEN"
```

### Soft-delete a customer

Requires `clients.manage`.

```bash
curl -X DELETE "$BASE_URL/customers/1" \
  -H "Authorization: Bearer $ACCESS_TOKEN"
```

## 7. Ticket APIs

### List and filter visible tickets

Employees see the service console. Client contacts see tickets belonging only to their client.

```bash
curl "$BASE_URL/tickets?page=1&limit=20&status=OPEN&priority=URGENT&search=invoice" \
  -H "Authorization: Bearer $ACCESS_TOKEN"
```

Ticket statuses: `OPEN`, `ACCEPTED`, `COMPLETED`, and `DECLINED`.

Priorities: `NORMAL`, `HIGH`, and `URGENT`.

### Get a ticket

The response includes attempts and visible conversation messages. Internal employee messages are hidden from client contacts.

```bash
curl "$BASE_URL/tickets/1" \
  -H "Authorization: Bearer $ACCESS_TOKEN"
```

### Raise a ticket

Only an authenticated client contact can raise a ticket. The selected location must belong to the client and be within the contact's location access.

```bash
curl -X POST "$BASE_URL/tickets" \
  -H "Authorization: Bearer $ACCESS_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "location_id": 1,
    "subject": "Invoice is not printing",
    "issue_description": "The application shows a printer error after saving an invoice.",
    "priority": "HIGH"
  }'
```

The server generates the service request number automatically.

### Accept an open ticket

Requires `tickets.manage`. Acceptance is atomic, so two employees cannot accept the same open ticket.

```bash
curl -X POST "$BASE_URL/tickets/1/accept" \
  -H "Authorization: Bearer $ACCESS_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{}'
```

### Release an accepted ticket

The accepting employee must provide a note. The attempt is preserved and the ticket returns to `OPEN`.

```bash
curl -X POST "$BASE_URL/tickets/1/release" \
  -H "Authorization: Bearer $ACCESS_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "note": "Client system is unavailable. Please retry after 4 PM."
  }'
```

### Complete an accepted ticket

The accepting employee must provide the final resolution.

```bash
curl -X POST "$BASE_URL/tickets/1/complete" \
  -H "Authorization: Bearer $ACCESS_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "note": "Printer configuration corrected and test invoice printed successfully."
  }'
```

### Decline a ticket

Requires `tickets.decline`, normally held by a service administrator. A reason is mandatory.

```bash
curl -X POST "$BASE_URL/tickets/1/decline" \
  -H "Authorization: Bearer $ACCESS_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "note": "Request is outside the agreed support scope."
  }'
```

### Change ticket priority

Requires `tickets.manage`.

```bash
curl -X PUT "$BASE_URL/tickets/1/priority" \
  -H "Authorization: Bearer $ACCESS_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "priority": "URGENT"
  }'
```

### Add a public conversation message

```bash
curl -X POST "$BASE_URL/tickets/1/messages" \
  -H "Authorization: Bearer $ACCESS_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "message": "Please call the client after 3 PM.",
    "is_internal": false
  }'
```

### Add an employee-only internal message

Only employees can create internal messages.

```bash
curl -X POST "$BASE_URL/tickets/1/messages" \
  -H "Authorization: Bearer $ACCESS_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "message": "Check the printer driver version before calling.",
    "is_internal": true
  }'
```

### Soft-delete a ticket

Requires `tickets.manage`. Only completed or declined tickets can be removed.

```bash
curl -X DELETE "$BASE_URL/tickets/1" \
  -H "Authorization: Bearer $ACCESS_TOKEN"
```

## 8. Analytics API

Requires `analytics.view`.

The response includes total/open/accepted/completed/declined counts, average resolution time, and employee performance.

```bash
curl "$BASE_URL/analytics?from=2026-06-01&to=2026-06-30" \
  -H "Authorization: Bearer $ACCESS_TOKEN"
```

If dates are omitted, the API uses the most recent 30-day period.

## 9. Employee and team APIs

These APIs require `employees.manage`.

### List employees and assigned roles

```bash
curl "$BASE_URL/users?page=1&limit=20" \
  -H "Authorization: Bearer $ACCESS_TOKEN"
```

### Suspend an employee

Suspension also suspends the employee login account.

```bash
curl -X POST "$BASE_URL/users/2/suspend" \
  -H "Authorization: Bearer $ACCESS_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "reason": "Employee is on extended leave."
  }'
```

### Create a team

```bash
curl -X POST "$BASE_URL/teams" \
  -H "Authorization: Bearer $ACCESS_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "team_name": "Service Team A",
    "description": "Primary retail support team."
  }'
```

### Add an employee to a team

```bash
curl -X POST "$BASE_URL/teams/1/members" \
  -H "Authorization: Bearer $ACCESS_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "employee_id": 2,
    "is_team_lead": true
  }'
```

## 10. Common HTTP status codes

| Status | Meaning |
|---:|---|
| `200` | Request succeeded |
| `201` | Resource created |
| `400` | Invalid request or ticket state transition |
| `401` | Missing, invalid, or expired authentication |
| `403` | Authenticated user lacks permission or tenant access |
| `404` | Resource or API endpoint not found |
| `422` | Field validation failed |
| `429` | Rate limit exceeded |
| `500` | Internal server error; details are logged but not exposed |

## 11. Permission summary

| Permission | Purpose |
|---|---|
| `tickets.manage` | Accept, release, complete, prioritize, and remove tickets |
| `tickets.decline` | Decline service tickets |
| `clients.manage` | Create, update, and remove clients |
| `employees.manage` | Manage employees, accounts, and teams |
| `leads.manage` | Manage leads |
| `analytics.view` | View executive analytics and employee performance |

`SYSTEM_ADMIN` automatically passes every permission check. Client-side `CLIENT_ADMIN` remains separate and never receives ONESALEZ employee permissions.

## 12. Complete lifecycle example

Typical service flow:

1. Client contact logs in using the `client` realm.
2. Client contact creates a ticket with `POST /tickets`.
3. Service employee logs in using the `employee` realm.
4. Employee accepts it with `POST /tickets/{id}/accept`.
5. Employee either releases it with a note or completes it with a resolution.
6. Every transition remains in service attempt and status history.

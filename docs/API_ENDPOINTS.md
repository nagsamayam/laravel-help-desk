# REST API Endpoints Specification

All API endpoints are versioned under `/api/v1` and return standardized JSON envelopes.

---

## Standard Response Format

### Success Envelope
```json
{
  "success": true,
  "message": "Operation completed successfully.",
  "data": { ... },
  "meta": { ... }
}
```

### Error Envelope
```json
{
  "success": false,
  "error": "ERROR_CODE",
  "message": "Human-readable explanation of error.",
  "details": {
    "field_name": [
      "Validation error message."
    ]
  }
}
```

---

## Authentication & Headers

| Header | Description | Required |
| :--- | :--- | :---: |
| `Authorization` | `Bearer <jwt_token>` for protected endpoints. | Conditional |
| `Accept` | `application/json` | Yes |
| `Content-Type` | `application/json` | On POST/PUT |
| `Idempotency-Key` | UUIDv4 string (e.g. `9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d`) for mutating requests (`POST /api/v1/tickets`, `PUT /api/v1/tickets/*`, state transitions, assignment). | Optional (Auto-injected by frontend) |

---

## Endpoints Catalog

### 1. Authentication & Users

#### Register User
- **Method / Path:** `POST /api/v1/auth/register`
- **Access:** Public
- **Request Body:**
  ```json
  {
    "first_name": "John",
    "last_name": "Doe",
    "email": "john.doe@example.com",
    "password": "Password123!",
    "password_confirmation": "Password123!",
    "role": "CUSTOMER"
  }
  ```

#### Login User
- **Method / Path:** `POST /api/v1/auth/login`
- **Access:** Public
- **Request Body:**
  ```json
  {
    "email": "john.doe@example.com",
    "password": "Password123!"
  }
  ```
- **Response:** Returns JWT token and authenticated user payload.

#### Get Current Profile
- **Method / Path:** `GET /api/v1/auth/me`
- **Access:** Authenticated (`Bearer <token>`)

#### Refresh Token
- **Method / Path:** `POST /api/v1/auth/refresh`
- **Access:** Authenticated (`Bearer <token>`)

#### Logout
- **Method / Path:** `POST /api/v1/auth/logout`
- **Access:** Authenticated (`Bearer <token>`)

#### List Available Agents
- **Method / Path:** `GET /api/v1/agents`
- **Access:** Staff only (`AGENT`, `ADMIN`)

#### List Users
- **Method / Path:** `GET /api/v1/users?role=AGENT`
- **Access:** Admin only (`ADMIN`)

---

### 2. Ticket Management & Specification Filtering

#### List Tickets (Specification Query)
- **Method / Path:** `GET /api/v1/tickets`
- **Access:** Authenticated (Customers see only own tickets; Agents/Admins see all)
- **Query Parameters:**
  - `status` (`string`): Filter by status (`OPEN`, `IN_PROGRESS`, `WAITING_FOR_CUSTOMER`, `RESOLVED`, `CLOSED`)
  - `priority` (`string`): Filter by priority (`LOW`, `MEDIUM`, `HIGH`, `URGENT`)
  - `category_id` (`integer`): Filter by category ID
  - `open` (`boolean`): Filter active non-closed tickets (`true` / `false`)
  - `urgent` (`boolean`): Filter high or urgent priority tickets (`true` / `false`)
  - `unassigned` (`boolean`): Filter unassigned tickets (`true` / `false`)
  - `assigned_to` (`integer`): Filter by assigned agent user ID
  - `customer_id` (`integer`): Filter by customer user ID
  - `overdue` (`boolean`): Filter SLA-breached tickets (`true` / `false`)
  - `page` (`integer`): Pagination page number (Default: `1`)
  - `per_page` (`integer`): Items per page (Default: `15`)

#### Create Ticket
- **Method / Path:** `POST /api/v1/tickets`
- **Access:** Authenticated (`CUSTOMER`, `AGENT`, `ADMIN`)
- **Headers:** `Idempotency-Key: <uuid>` (Optional, recommended)
- **Request Body:**
  ```json
  {
    "subject": "Cannot access billing portal",
    "description": "Getting a 500 error when clicking on the invoices tab.",
    "category_id": 2,
    "priority": "HIGH"
  }
  ```

#### Get Ticket Details
- **Method / Path:** `GET /api/v1/tickets/{id}`
- **Access:** Authenticated (Owner, Assigned Agent, or Admin)
- **Response Data:** Includes full ticket model with eager-loaded `category`, `customer`, and `assignee` relations.

#### Update Ticket Metadata
- **Method / Path:** `PUT /api/v1/tickets/{id}`
- **Access:** Staff (`AGENT`, `ADMIN`) or Owner (if open)
- **Headers:** `Idempotency-Key: <uuid>`
- **Request Body:**
  ```json
  {
    "subject": "Cannot access billing invoices tab",
    "description": "Updated error details with screenshot reference.",
    "category_id": 2,
    "priority": "URGENT"
  }
  ```

#### Delete Ticket
- **Method / Path:** `DELETE /api/v1/tickets/{id}`
- **Access:** Admin only (`ADMIN`)

---

### 3. Ticket State Machine Lifecycle

#### Generic State Transition
- **Method / Path:** `POST /api/v1/tickets/{id}/transition`
- **Access:** Staff (`AGENT`, `ADMIN`)
- **Headers:** `Idempotency-Key: <uuid>`
- **Request Body:**
  ```json
  {
    "status": "WAITING_FOR_CUSTOMER",
    "reason": "Requested additional log files from customer."
  }
  ```

#### Resolve Ticket
- **Method / Path:** `POST /api/v1/tickets/{id}/resolve`
- **Access:** Staff (`AGENT`, `ADMIN`)
- **Headers:** `Idempotency-Key: <uuid>`
- **Request Body:**
  ```json
  {
    "resolution_notes": "Applied firewall whitelist update for customer subnet."
  }
  ```

#### Close Ticket
- **Method / Path:** `POST /api/v1/tickets/{id}/close`
- **Access:** Authenticated (Customer on own resolved ticket, or Staff)
- **Headers:** `Idempotency-Key: <uuid>`
- **Request Body:**
  ```json
  {
    "reason": "Confirmed issue resolved."
  }
  ```

#### Reopen Ticket
- **Method / Path:** `POST /api/v1/tickets/{id}/reopen`
- **Access:** Authenticated (Customer on own closed/resolved ticket, or Staff)
- **Headers:** `Idempotency-Key: <uuid>`
- **Request Body:**
  ```json
  {
    "reason": "Issue reoccurred after server reboot."
  }
  ```

---

### 4. Ticket Assignment (Strategy Pattern)

#### Assign Ticket
- **Method / Path:** `POST /api/v1/tickets/{id}/assign`
- **Access:** Staff (`AGENT`, `ADMIN`)
- **Headers:** `Idempotency-Key: <uuid>`
- **Request Body (Manual Assignment):**
  ```json
  {
    "agent_id": 4,
    "reason": "Assigned to database specialist"
  }
  ```
- **Request Body (Strategy Resolution):**
  ```json
  {
    "strategy": "round_robin"
  }
  ```
  *Supported strategies:* `"round_robin"`, `"least_busy"`, `"skill_based"`.

---

### 5. Ticket Routing (Chain of Responsibility)

#### Evaluate Routing Pipeline
- **Method / Path:** `POST /api/v1/tickets/{id}/route`
- **Access:** Staff (`AGENT`, `ADMIN`)
- **Headers:** `Idempotency-Key: <uuid>`
- **Request Body:**
  ```json
  {
    "persist": true
  }
  ```
- **Response:** Returns evaluated routing decision, matched rule name (`VipRoutingRule`, `UrgentPriorityRoutingRule`, `CategoryRoutingRule`, `DefaultRoutingRule`), target queue, priority, and assigned agent.

---

### 6. Conversations & Messaging

#### List Messages
- **Method / Path:** `GET /api/v1/tickets/{id}/messages`
- **Access:** Authenticated (Customer sees only public messages; Staff sees all messages including internal notes)

#### Post Message / Reply
- **Method / Path:** `POST /api/v1/tickets/{id}/messages`
- **Access:** Authenticated (Customer on own ticket; Staff on any ticket)
- **Headers:** `Idempotency-Key: <uuid>`
- **Request Body:**
  ```json
  {
    "body": "Here are the requested application log files.",
    "is_internal": false
  }
  ```
  *(Note: `is_internal: true` is restricted to Staff only).*

---

### 7. Lifecycle History & Audit Logs

#### Get Ticket Status History
- **Method / Path:** `GET /api/v1/tickets/{id}/status-history`
- **Access:** Authenticated (Owner, Assigned Agent, Admin)
- **Response Data:** Array of status transition records with timestamps, actor details, and transition reasons.

#### Get Ticket Audit Logs
- **Method / Path:** `GET /api/v1/tickets/{id}/audit-logs`
- **Access:** Staff only (`AGENT`, `ADMIN`)
- **Response Data:** Array of field-level mutation diffs (`old_values` vs `new_values`), actor ID, IP address, and user agent.

#### Browse Global Audit Logs
- **Method / Path:** `GET /api/v1/audit-logs`
- **Access:** Admin only (`ADMIN`)
- **Query Parameters:**
  - `user_id` (`integer`): Filter by actor user ID
  - `action` (`string`): Filter by action (`created`, `updated`, `deleted`, `transitioned`, `assigned`, `routed`)
  - `auditable_type` (`string`): Filter by target model class or alias
  - `auditable_id` (`string`): Filter by target resource ID
  - `date_from` (`string`): YYYY-MM-DD
  - `date_to` (`string`): YYYY-MM-DD

---

### 8. Ticket Attachments & Chunked Uploads

For in-depth architecture details and constraints, see [Ticket Attachment Subsystem](TICKET_ATTACHMENTS.md).

#### Direct Single-File Upload
- **Method / Path:** `POST /api/v1/attachments/upload`
- **Access:** Authenticated
- **Content-Type:** `multipart/form-data`
- **Payload:** `file` (Binary file <= 5 MB; PDF, PNG, JPEG)

#### Initialize Chunked Upload
- **Method / Path:** `POST /api/v1/attachments/chunk/init`
- **Access:** Authenticated
- **Request Body:**
  ```json
  {
    "file_name": "server_diagnostics.pdf",
    "file_size": 4194304,
    "mime_type": "application/pdf",
    "total_chunks": 4
  }
  ```

#### Upload Chunk Slice
- **Method / Path:** `POST /api/v1/attachments/chunk`
- **Access:** Authenticated
- **Content-Type:** `multipart/form-data`
- **Payload:** `upload_id` (string), `chunk_index` (integer), `chunk` (binary blob)

#### Complete & Assemble Chunked Upload
- **Method / Path:** `POST /api/v1/attachments/chunk/complete`
- **Access:** Authenticated
- **Request Body:**
  ```json
  {
    "upload_id": "b7d91e6b-74df-4122-8356-834c56be6fa0"
  }
  ```

#### Generate S3 Pre-signed Upload URL
- **Method / Path:** `POST /api/v1/attachments/presigned-url`
- **Access:** Authenticated
- **Request Body:**
  ```json
  {
    "file_name": "error_capture.png",
    "file_size": 1048576,
    "mime_type": "image/png"
  }
  ```

#### View / Preview Attachment Inline
- **Method / Path:** `GET /api/v1/attachments/{id}/view`
- **Access:** Authenticated (Owner, Assigned Agent, Admin)
- **Response:** Raw binary file with `Content-Disposition: inline`

#### Download Attachment
- **Method / Path:** `GET /api/v1/attachments/{id}/download`
- **Access:** Authenticated (Owner, Assigned Agent, Admin)
- **Response:** Raw binary file with `Content-Disposition: attachment`

---

### 9. Real-Time Broadcasting & WebSockets

For full WebSocket channels, events, and React Echo setup, see [Broadcasting Subsystem](REVERB_BROADCASTING.md).

#### Authenticate WebSocket Channel
- **Method / Path:** `POST /broadcasting/auth`
- **Access:** Authenticated (`Bearer <jwt_token>`)
- **Headers:** `Authorization: Bearer <jwt_token>`
- **Request Body:**
  ```json
  {
    "socket_id": "12345.67890",
    "channel_name": "presence-tickets.42"
  }
  ```

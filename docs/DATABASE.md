# Database Design & Schema

## Relational Architecture

The application is backed by **MySQL 8.4 LTS** as the authoritative source of truth, enforcing data integrity through strict foreign key constraints, composite unique indexes, and ACID transactions.

```text
┌────────────────┐          ┌────────────────────────┐          ┌───────────────────────┐
│     users      │1       * │        tickets         │1       * │    ticket_messages    │
├────────────────┼──────────┼────────────────────────┼──────────┼───────────────────────┤
│ id             │          │ id                     │          │ id                    │
│ first_name     │          │ customer_id (FK:users) │          │ ticket_id (FK:tickets)│
│ last_name      │          │ assigned_to (FK:users) │          │ user_id (FK:users)    │
│ email          │          │ category_id (FK:categ) │          │ message / body        │
│ password       │          │ subject                │          │ is_internal           │
│ role           │          │ description            │          │ created_at            │
│ created_at     │          │ status                 │          │ updated_at            │
│ updated_at     │          │ priority               │          └───────────────────────┘
└───────┬────────┘          │ resolution_notes       │
        │                   │ due_at                 │
        │1                  │ resolved_at            │
        │                   │ closed_at              │
        │                   │ created_at             │
        │                   │ updated_at             │
        │                   └───────────┬────────────┘
        │                               │
        │                               │1
        │                               │
        │                               ▼ *
        │                   ┌────────────────────────┐
        │                   │ ticket_status_histories│
        │                   ├────────────────────────┤
        │                   │ id                     │
        │                   │ ticket_id (FK:tickets) │
        │                   │ from_status            │
        │                   │ to_status              │
        │                   │ changed_by (FK:users)  │
        │                   │ reason                 │
        │                   │ created_at             │
        │                   └────────────────────────┘
        ▼ *
┌────────────────────────┐          ┌────────────────────────┐
│       audit_logs       │          │    idempotency_keys    │
├────────────────────────┤          ├────────────────────────┤
│ id                     │          │ id                     │
│ user_id (FK:users)     │          │ scope_type             │
│ action                 │          │ scope_id               │
│ auditable_type         │          │ operation              │
│ auditable_id           │          │ key_hash (CHAR 64)     │
│ old_values (JSON)      │          │ request_hash (CHAR 64) │
│ new_values (JSON)      │          │ response_status        │
│ ip_address             │          │ response_body (JSON)   │
│ user_agent             │          │ response_headers (JSON)│
│ created_at             │          │ resource_type          │
└────────────────────────┘          │ resource_id            │
                                    │ expires_at             │
                                    │ completed_at           │
                                    │ created_at             │
                                    └────────────────────────┘
```

---

## Detailed Table Specifications

### 1. `users`
Represents customer and staff accounts with role-based access control.

| Column | Type | Attributes | Description |
| :--- | :--- | :--- | :--- |
| `id` | `BIGINT UNSIGNED` | `AUTO_INCREMENT, PK` | Primary key |
| `first_name` | `VARCHAR(100)` | `NOT NULL` | User's first name |
| `last_name` | `VARCHAR(100)` | `NOT NULL` | User's last name |
| `email` | `VARCHAR(255)` | `UNIQUE, NOT NULL` | Login email address |
| `password` | `VARCHAR(255)` | `NOT NULL` | Bcrypt hashed password |
| `role` | `VARCHAR(20)` | `NOT NULL, DEFAULT 'CUSTOMER'` | Role enum (`ADMIN`, `AGENT`, `CUSTOMER`) |
| `created_at` | `TIMESTAMP` | `NULL` | Creation timestamp |
| `updated_at` | `TIMESTAMP` | `NULL` | Last update timestamp |

---

### 2. `categories`
Support issue classifications (e.g., Billing, Technical, Security).

| Column | Type | Attributes | Description |
| :--- | :--- | :--- | :--- |
| `id` | `BIGINT UNSIGNED` | `AUTO_INCREMENT, PK` | Primary key |
| `name` | `VARCHAR(100)` | `UNIQUE, NOT NULL` | Category name |
| `description` | `TEXT` | `NULL` | Optional description |
| `is_active` | `TINYINT(1)` | `NOT NULL, DEFAULT 1` | Active status flag |
| `created_at` | `TIMESTAMP` | `NULL` | Creation timestamp |
| `updated_at` | `TIMESTAMP` | `NULL` | Last update timestamp |

---

### 3. `tickets`
The core aggregate root managing support requests, lifecycle statuses, priorities, and SLA deadlines.

| Column | Type | Attributes | Description |
| :--- | :--- | :--- | :--- |
| `id` | `BIGINT UNSIGNED` | `AUTO_INCREMENT, PK` | Primary key |
| `customer_id` | `BIGINT UNSIGNED` | `FK -> users.id, NOT NULL` | Submitting customer |
| `assigned_to` | `BIGINT UNSIGNED` | `FK -> users.id, NULL` | Assigned support agent |
| `category_id` | `BIGINT UNSIGNED` | `FK -> categories.id, NOT NULL` | Issue category |
| `subject` | `VARCHAR(255)` | `NOT NULL` | Ticket subject line |
| `description` | `TEXT` | `NOT NULL` | Detailed issue description |
| `status` | `VARCHAR(30)` | `NOT NULL, DEFAULT 'OPEN'` | Status enum (`OPEN`, `IN_PROGRESS`, `WAITING_FOR_CUSTOMER`, `RESOLVED`, `CLOSED`) |
| `priority` | `VARCHAR(20)` | `NOT NULL, DEFAULT 'MEDIUM'` | Priority enum (`LOW`, `MEDIUM`, `HIGH`, `URGENT`) |
| `resolution_notes` | `TEXT` | `NULL` | Notes provided upon resolution |
| `due_at` | `TIMESTAMP` | `NULL` | SLA deadline timestamp |
| `resolved_at` | `TIMESTAMP` | `NULL` | Timestamp when ticket was marked resolved |
| `closed_at` | `TIMESTAMP` | `NULL` | Timestamp when ticket was closed |
| `created_at` | `TIMESTAMP` | `NULL` | Creation timestamp |
| `updated_at` | `TIMESTAMP` | `NULL` | Last update timestamp |

**Indexes:**
- `INDEX tickets_customer_id_index (customer_id)`
- `INDEX tickets_assigned_to_index (assigned_to)`
- `INDEX tickets_category_id_index (category_id)`
- `INDEX tickets_status_index (status)`
- `INDEX tickets_priority_index (priority)`
- `INDEX tickets_due_at_index (due_at)`

---

### 4. `ticket_messages`
Conversation history and internal staff notes associated with tickets.

| Column | Type | Attributes | Description |
| :--- | :--- | :--- | :--- |
| `id` | `BIGINT UNSIGNED` | `AUTO_INCREMENT, PK` | Primary key |
| `ticket_id` | `BIGINT UNSIGNED` | `FK -> tickets.id, CASCADE` | Parent ticket |
| `user_id` | `BIGINT UNSIGNED` | `FK -> users.id, CASCADE` | Author of message |
| `message` | `TEXT` | `NOT NULL` | Message content body |
| `is_internal` | `TINYINT(1)` | `NOT NULL, DEFAULT 0` | Flag for internal staff-only notes |
| `created_at` | `TIMESTAMP` | `NULL` | Creation timestamp |
| `updated_at` | `TIMESTAMP` | `NULL` | Last update timestamp |

---

### 5. `ticket_status_histories`
Dedicated domain-specific timeline recording every status transition, the acting user, and the reason.

| Column | Type | Attributes | Description |
| :--- | :--- | :--- | :--- |
| `id` | `BIGINT UNSIGNED` | `AUTO_INCREMENT, PK` | Primary key |
| `ticket_id` | `BIGINT UNSIGNED` | `FK -> tickets.id, CASCADE` | Parent ticket |
| `from_status` | `VARCHAR(30)` | `NOT NULL` | Status prior to transition |
| `to_status` | `VARCHAR(30)` | `NOT NULL` | New status |
| `changed_by` | `BIGINT UNSIGNED` | `FK -> users.id, NULL` | User who triggered transition |
| `reason` | `TEXT` | `NULL` | Transition reason or resolution notes |
| `created_at` | `TIMESTAMP` | `NOT NULL, useCurrent()` | Creation timestamp |

---

### 6. `audit_logs`
Generic audit logging tracking mutations across all system entities with JSON diffs.

| Column | Type | Attributes | Description |
| :--- | :--- | :--- | :--- |
| `id` | `BIGINT UNSIGNED` | `AUTO_INCREMENT, PK` | Primary key |
| `user_id` | `BIGINT UNSIGNED` | `FK -> users.id, NULL` | User performing action |
| `action` | `VARCHAR(50)` | `NOT NULL` | Action name (`created`, `updated`, `deleted`, `assigned`, etc.) |
| `auditable_type` | `VARCHAR(120)` | `NOT NULL` | Polymorphic model class name |
| `auditable_id` | `VARCHAR(64)` | `NOT NULL` | Target model ID (supports int, UUID, ULID) |
| `old_values` | `JSON` | `NULL` | Previous attributes before mutation (sensitive fields masked) |
| `new_values` | `JSON` | `NULL` | New attributes after mutation |
| `ip_address` | `VARCHAR(45)` | `NULL` | Client IP address |
| `user_agent` | `TEXT` | `NULL` | Client user agent string |
| `created_at` | `TIMESTAMP` | `NOT NULL, useCurrent()` | Creation timestamp |

**Indexes:**
- `INDEX audit_logs_auditable_type_auditable_id_index (auditable_type, auditable_id)`
- `INDEX audit_logs_user_id_index (user_id)`
- `INDEX audit_logs_action_index (action)`
- `INDEX audit_logs_created_at_index (created_at)`

---

### 7. `idempotency_keys`
Durable source of truth for transactional idempotency and deterministic replay.

| Column | Type | Attributes | Description |
| :--- | :--- | :--- | :--- |
| `id` | `BIGINT UNSIGNED` | `AUTO_INCREMENT, PK` | Primary key |
| `scope_type` | `VARCHAR(32)` | `NOT NULL` | Namespace scope type (`'user'`, `'tenant'`) |
| `scope_id` | `VARCHAR(64)` | `NOT NULL` | Scope identifier (supports integers, UUIDs, ULIDs) |
| `operation` | `VARCHAR(100)` | `NOT NULL` | Named operation route (`'tickets.store'`, etc.) |
| `key_hash` | `CHAR(64)` | `NOT NULL` | SHA-256 hash of raw `Idempotency-Key` header |
| `request_hash` | `CHAR(64)` | `NOT NULL` | SHA-256 canonical hash of validated request payload |
| `response_status` | `SMALLINT UNSIGNED`| `NULL` | Original HTTP status code (e.g. 201) |
| `response_body` | `JSON` | `NULL` | Stored JSON response body for replay |
| `response_headers` | `JSON` | `NULL` | Stored HTTP headers (e.g., Location, Content-Type) |
| `resource_type` | `VARCHAR(100)` | `NULL` | Target domain resource type (e.g. `'ticket'`) |
| `resource_id` | `VARCHAR(64)` | `NULL` | Target resource identifier |
| `expires_at` | `TIMESTAMP` | `NOT NULL` | Authoritative expiration timestamp |
| `completed_at` | `TIMESTAMP` | `NULL` | Completion timestamp (`null` if in-flight) |
| `created_at` | `TIMESTAMP` | `NULL` | Creation timestamp |

**Constraints & Indexes:**
- `UNIQUE INDEX idempotency_keys_scope_operation_key_unique (scope_type, scope_id, operation, key_hash)`
- `INDEX idempotency_keys_expires_at_index (expires_at)`
- `INDEX idempotency_keys_resource_type_resource_id_index (resource_type, resource_id)`

# Database Design

## Current database direction

Database: MySQL 8.4 LTS.

The schema will evolve incrementally as features are implemented.

## Initial tables

### users

Expected responsibilities:

- authentication
- customer/agent/admin identity
- role information

### categories

Ticket categorization.

Potential fields:

- id
- name
- description
- is_active
- timestamps

### tickets

Potential initial fields:

- id
- customer_id
- assigned_to
- category_id
- subject
- description
- status
- priority
- timestamps

Important indexes will be derived from actual access patterns.

Potential indexes:

- assigned_to + status
- customer_id + status
- category_id + status
- status + priority

Ticket creation idempotency is intentionally **not** stored on `tickets`. It is handled by the dedicated `idempotency_keys` table below so the mechanism can be reused by other mutating API operations.

### idempotency_keys

Purpose: durable, transactional API idempotency for mutating operations.

Fields:

- id
- scope_type
- scope_id
- operation
- key_hash
- request_hash
- response_status
- response_body
- resource_type
- resource_id
- expires_at
- completed_at
- timestamps

Constraints/indexes:

- unique scope_type + scope_id + operation + key_hash
- index expires_at
- index resource_type + resource_id

The raw `Idempotency-Key` is not stored. Only its SHA-256 hash is persisted.

The request fingerprint is a SHA-256 hash of a canonical representation of the operation and validated business payload. The raw request payload is not persisted by the idempotency subsystem.

Current scope is the authenticated user. The `scope_type`/`scope_id` design leaves room for a future tenant-scoped implementation without changing the core idempotency model.

The stored response status/body are required for deterministic replay of the original API response.

Default retention is 24 hours and is configurable through `IDEMPOTENCY_TTL_SECONDS`.

Expired records should be pruned regularly.

## ticket_messages

Potential fields:

- id
- ticket_id
- user_id
- body
- timestamps

## ticket_status_histories

Purpose: domain-specific history of ticket lifecycle transitions.

Potential fields:

- id
- ticket_id
- from_status
- to_status
- changed_by
- reason
- created_at

## audit_logs

Purpose: generic record of important changes.

Potential fields:

- id
- user_id
- action
- auditable_type
- auditable_id
- old_values
- new_values
- ip_address
- user_agent
- created_at

## Status history vs audit log

Both are intentionally planned.

Ticket status history answers:

> How did this ticket move through its lifecycle?

Audit log answers:

> Who changed what, when, and what were old/new values?

They should not be treated as the same table.

## Idempotency transaction model

For a database-backed command, the idempotency record and the business mutation are committed in the same MySQL transaction.

A concurrent request using the same scoped operation/key waits on the unique constraint. Once the first transaction commits, the second request observes the existing completed idempotency record and replays the stored response. If the first transaction rolls back, the second request can proceed normally.

This gives strong idempotency for transactional database mutations without using Redis as the source of truth.

The mechanism does **not** claim exactly-once execution for arbitrary external side effects. External email/SMS/HTTP integrations should use an outbox/event design when introduced.

## Concurrency

Assignment strategies may require transactions and row locking.

Examples:

- round-robin cursor updates
- concurrent ticket assignment
- preventing duplicate assignment decisions

Idempotency is now handled as a first-class database concern because it directly protects mutating API operations from duplicate submissions.

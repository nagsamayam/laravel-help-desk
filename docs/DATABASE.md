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
- idempotency_key
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

- unique idempotency_key
- assigned_to + status
- customer_id + status
- category_id + status
- status + priority

### ticket_messages

Potential fields:

- id
- ticket_id
- user_id
- body
- timestamps

### ticket_status_histories

Purpose: domain-specific history of ticket lifecycle transitions.

Potential fields:

- id
- ticket_id
- from_status
- to_status
- changed_by
- reason
- created_at

### audit_logs

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

> Who changed what, when, and what were the old/new values?

They should not be treated as the same table.

## Idempotency

Ticket creation should support an idempotency key.

For the current single-tenant implementation:

- idempotency_key should be unique.

Future multi-tenant implementation:

- likely use a composite unique constraint such as tenant_id + idempotency_key.

For a more general idempotency mechanism, a dedicated idempotency_keys table may eventually be preferable.

## Concurrency

Assignment strategies may require transactions and row locking.

Examples:

- round-robin cursor updates
- concurrent ticket assignment
- preventing duplicate assignment decisions

Do not solve concurrency prematurely; add the appropriate protection when the relevant feature is implemented.

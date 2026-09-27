# HelpDesk Project

## Overview

HelpDesk is a backend-first customer support REST API built as a learning project.

The primary goal is to refresh and deepen knowledge of:

- PHP 8.5
- Laravel 13
- relational database design
- Redis
- REST API design
- design patterns
- testing
- production-oriented backend engineering

## Current product scope

The first version is a single-tenant HelpDesk.

Core workflow:

Customer creates a ticket → ticket is assigned to an agent → customer and agent exchange messages → ticket moves through its lifecycle → ticket is resolved/closed and can be reopened when appropriate.

## Planned users

### Customer
- Create tickets
- View own tickets
- Add messages
- View ticket history
- Close/reopen tickets where permitted

### Agent
- View assigned tickets
- Reply to tickets
- Change status
- Change priority
- Assign/reassign tickets

### Admin
- Manage users/agents
- Manage categories
- View/manage tickets
- Configure HelpDesk behavior

## Current technical scope

- REST API
- Authentication and authorization
- Ticket management
- Ticket conversations
- Categories
- Assignment
- Ticket status history
- Audit logging
- Idempotent ticket creation
- Events/listeners
- Queues/jobs
- Redis
- Notifications
- Automated tests

## Deferred

- Blade frontend
- React frontend
- Vue frontend
- Multi-tenant implementation
- Billing/subscriptions
- Advanced AI features

## Future direction

Convert the HelpDesk into a multi-tenant SaaS platform while preserving tenant isolation and introducing:

- tenants
- tenant-aware users/data
- tenant-aware queues/cache/files
- plans/subscriptions
- usage limits
- billing
- AI usage tracking

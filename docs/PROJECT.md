# HelpDesk Project

## Overview

HelpDesk is a modern, production-grade customer support platform featuring a **Laravel REST API** and a **React 19 Single Page Application (SPA)** styled with Tailwind CSS v4 and shadcn/ui.

The primary goals of the project include:

- Modern PHP 8.2+ and Laravel 11/12 best practices
- Relational database design with MySQL 8.4 LTS
- Redis for caching, concurrency locks, and background queue workers
- Clean Domain-Driven Design (DDD) with bounded contexts and domain service providers
- Enterprise software design patterns (State, Strategy, Chain of Responsibility, Command/Action, Decorator, Events/Observer, Specification)
- Production-grade transactional idempotency with replay cache and collision backoff
- Multi-stakeholder email notifications and scheduled background maintenance jobs
- Granular role-based authorization via Laravel Policies
- High test coverage with Pest / PHPUnit test suites

---

## Current Product Scope

The application is fully operational as a **single-tenant HelpDesk** with complete end-to-end frontend and backend capabilities.

### Core Workflow:
1. **Ticket Creation:** A customer or agent submits a ticket with an automatic UUIDv4 `Idempotency-Key` header.
2. **Automated Triage & Routing:** The ticket triggers domain lifecycle events, creating audit logs and notifying admins/agents. The Chain of Responsibility router evaluates rules (`VIP`, `Urgent`, `Category`, `Default`).
3. **Assignment:** The ticket is assigned to a support agent manually or via strategies (`RoundRobin`, `LeastBusy`, `SkillBased`).
4. **Conversations:** Customers and agents exchange public messages; agents/admins can post internal staff notes hidden from customers.
5. **State Progression:** Tickets move through strictly validated state transitions (`Open` → `InProgress` → `WaitingForCustomer` → `Resolved` → `Closed`) with resolution notes and status history tracking.
6. **Background SLA & Maintenance:** Scheduled queue workers identify overdue tickets, escalate priorities, and auto-close inactive resolved tickets.

---

## User Personas & Permissions

### Customer
- Create tickets via modal form with automatic idempotency protection.
- View and filter their own tickets.
- Post replies in the conversation feed.
- View ticket status history.
- Close resolved tickets or reopen closed tickets they own.

### Agent
- View assigned and unassigned tickets across the organization.
- Post public replies and internal staff notes.
- Transition ticket statuses (`In Progress`, `Waiting for Customer`, `Resolved`).
- Assign or reassign tickets manually or execute automated assignment strategies.
- Evaluate routing pipelines.
- View ticket lifecycle history and audit logs.

### Admin
- Full system superuser access across all tickets, users, categories, and audit logs.
- Manage staff and customer user accounts.
- Delete tickets with audit logging.
- Browse system-wide audit logs with JSON mutation diffs (`old_values` vs `new_values`).

---

## Technical Implementations (Completed)

- [x] **REST API & Delivery Layer:** Standardized JSON envelopes (`ApiResponse`), FormRequests, API resources, and role-based route middleware.
- [x] **Domain-Driven Design (DDD):** Modularized bounded contexts (`Domain/Ticket`, `Domain/Identity`, `Domain/Audit`) and domain service providers.
- [x] **Software Design Patterns:** State, Strategy, Chain of Responsibility, Command/Action, Decorator, Events/Observer, and Specification patterns.
- [x] **Transactional Idempotency:** Subsystem with database authority, Redis replay cache, distributed locks, in-flight backoff (`409 Conflict`), and `IdempotentRequest` middleware.
- [x] **Transactional Email Notifications:** Responsive Blade mailables with logging/metrics/retry decorators and stakeholder notification routing.
- [x] **Asynchronous Background Queues:** Queued event listeners, unique jobs (`ShouldBeUnique`), concurrency locks (`WithoutOverlapping`), and scheduled maintenance CLI commands.
- [x] **React 19 SPA Frontend:** Vite, Tailwind CSS v4, shadcn/ui components, TanStack React Query v5, Zustand state store, and Lucide React icons.
- [x] **Seeders & Test Data:** Realistic database seeders (`UserSeeder`, `CategorySeeder`, `TicketSeeder`) for development and testing.
- [x] **Automated Test Suite:** 106+ unit and feature tests covering all patterns, APIs, authorization, and background jobs.

---

## Future Roadmap

The future evolution of HelpDesk will focus on:
1. **AI Capabilities (Laravel AI SDK):** Automated ticket classification, sentiment analysis, smart reply generation, and RAG-based knowledge base search.
2. **Multi-Tenant SaaS:** Tenant isolation, tenant-aware database scoping, custom branding, subscription billing, and usage quotas.

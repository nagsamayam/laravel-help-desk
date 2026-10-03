# Real-Time Broadcasting Subsystem (Laravel Reverb & WebSockets)

## Overview

The HelpDesk platform incorporates real-time WebSocket communication powered by **Laravel Reverb**, **Redis Pub/Sub**, and **Laravel Echo with Pusher-JS** in the React 19 Single Page Application.

This subsystem provides instant multi-agent collision detection, live conversation streaming, dynamic ticket status synchronization, and an interactive real-time broadcast activity drawer.

```text
┌────────────────────────────────────────────────────────────────────────────────────────┐
│                                   React 19 Frontend                                    │
│                                                                                        │
│   ┌───────────────────────────┐  ┌───────────────────────────┐  ┌──────────────────┐   │
│   │     useBroadcasting()     │  │   useTicketRealtime()     │  │ Broadcast Drawer │   │
│   │ (Global feed, user chans) │  │(Presence & Collision Det.)│  │ (Live UI Feed)   │   │
│   └─────────────┬─────────────┘  └─────────────┬─────────────┘  └────────▲─────────┘   │
│                 │                              │                         │             │
│                 ▼                              ▼                         │             │
│   ┌──────────────────────────────────────────────────────────┐           │             │
│   │             Laravel Echo + Pusher JS Client              │───────────┘             │
│   │           (JWT Bearer auth on /broadcasting/auth)        │                         │
│   └─────────────────────────────▲────────────────────────────┘                         │
└─────────────────────────────────┼──────────────────────────────────────────────────────┘
                                  │ WebSockets (ws://localhost:8080)
                                  ▼
┌────────────────────────────────────────────────────────────────────────────────────────┐
│                              Laravel Reverb Server                                     │
│                              (0.0.0.0:8080 Event Hub)                                  │
└─────────────────────────────────▲──────────────────────────────────────────────────────┘
                                  │ Redis Pub/Sub Event Transport
                                  ▼
┌────────────────────────────────────────────────────────────────────────────────────────┐
│                             Laravel Backend Application                                │
│                                                                                        │
│   ┌───────────────────────────┐                 ┌──────────────────────────────────┐   │
│   │   Domain Events & Jobs    │                 │       Broadcast Channel Auth     │   │
│   │(TicketCreated, Message...)│                 │  (routes/channels.php via JWT)   │   │
│   └─────────────┬─────────────┘                 └──────────────────────────────────┘   │
│                 │ (ShouldBroadcast queued on Redis)                                    │
│                 ▼                                                                      │
│   ┌───────────────────────────┐                                                        │
│   │    Redis Queue Worker     │                                                        │
│   │   (php artisan queue:work)│                                                        │
│   └───────────────────────────┘                                                        │
└────────────────────────────────────────────────────────────────────────────────────────┘
```

---

## 1. Channel Hierarchy & Authorization Matrix

All WebSocket channels are private or presence channels requiring authenticated token validation against `POST /broadcasting/auth`.

| Channel Name | Channel Type | Authorized Personas | Purpose |
| :--- | :--- | :--- | :--- |
| `private-users.{userId}` | Private Channel | The matching `User` (`user.id === userId`) | Personal notifications: new tickets created, assignments, and ticket status changes. |
| `private-agent.feed` | Private Channel | Support Staff (`AGENT`, `ADMIN`) | Global support inbox stream: alerts agents in real time whenever any ticket is created or updated. |
| `presence-tickets.{ticketId}` | Presence Channel | Ticket Owner (`CUSTOMER`), Support Staff (`AGENT`, `ADMIN`) | Live ticket room: tracks active viewers (collision detection), live customer & staff replies, and status transitions. |
| `private-tickets.{ticketId}.internal` | Private Channel | Support Staff (`AGENT`, `ADMIN`) | Staff-only live internal discussion and confidential staff notes. |

---

## 2. Broadcast Events & Payload Specifications

Events implement `Illuminate\Contracts\Broadcasting\ShouldBroadcast` and specify discrete broadcast names using the `broadcastAs()` method:

### 1. `TicketCreated`
- **Broadcast Name:** `ticket.created`
- **Channels:** `private-agent.feed`, `private-users.{customerId}`
- **Payload:**
  ```json
  {
    "ticket": {
      "id": 42,
      "subject": "Unable to access billing dashboard",
      "status": "OPEN",
      "priority": "HIGH",
      "category_id": 2,
      "customer_id": 15,
      "created_at": "2026-10-03T14:00:00Z"
    }
  }
  ```

### 2. `TicketStatusChanged`
- **Broadcast Name:** `ticket.status.changed`
- **Channels:** `presence-tickets.{ticketId}`, `private-agent.feed`, `private-users.{customerId}`
- **Payload:**
  ```json
  {
    "ticket_id": 42,
    "old_status": "OPEN",
    "new_status": "IN_PROGRESS",
    "changed_by": {
      "id": 3,
      "name": "Sarah Support",
      "role": "AGENT"
    }
  }
  ```

### 3. `TicketAssigned`
- **Broadcast Name:** `ticket.assigned`
- **Channels:** `presence-tickets.{ticketId}`, `private-agent.feed`, `private-users.{agentId}`, `private-users.{previousAgentId}`
- **Payload:**
  ```json
  {
    "ticket_id": 42,
    "assigned_to": {
      "id": 5,
      "name": "Alex Tech",
      "role": "AGENT"
    },
    "assigned_by": {
      "id": 1,
      "name": "Admin Superuser"
    }
  }
  ```

### 4. `TicketMessageAdded`
- **Broadcast Name:** `ticket.message.added`
- **Channels:**
  - Public reply: `presence-tickets.{ticketId}`
  - Internal note: `private-tickets.{ticketId}.internal`, `private-agent.feed`
- **Payload:**
  ```json
  {
    "ticket_id": 42,
    "message": {
      "id": 104,
      "ticket_id": 42,
      "user_id": 15,
      "user": {
        "id": 15,
        "name": "Alice Customer",
        "role": "CUSTOMER"
      },
      "message": "Here is the error screenshot you requested.",
      "is_internal": false,
      "created_at": "2026-10-03T14:15:00Z",
      "attachments": []
    }
  }
  ```

---

## 3. JWT Channel Authentication

Because the REST API is authenticated via JWT tokens (`Bearer <token>`), channel authorization requests to `/broadcasting/auth` are intercepted by the `api` auth guard.

### Authentication Endpoint (`routes/channels.php`)
```php
Broadcast::channel('users.{id}', function (User $user, int $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('agent.feed', function (User $user) {
    return $user->isAgent() || $user->isAdmin();
});

Broadcast::channel('tickets.{ticketId}', function (User $user, int $ticketId) {
    $ticket = Ticket::find($ticketId);
    if (! $ticket) return false;
    
    if ($user->isAdmin() || $user->isAgent() || (int) $ticket->customer_id === (int) $user->id) {
        return [
            'id' => $user->id,
            'name' => $user->first_name . ' ' . $user->last_name,
            'email' => $user->email,
            'role' => $user->role->value,
        ];
    }
    return false;
});
```

---

## 4. React Frontend Architecture

### 1. Echo Singleton (`resources/js/lib/echo.js`)
Configures a centralized Laravel Echo instance connected to Laravel Reverb:
- Configures `wsHost`, `wsPort`, `forceTLS`, and `enabledTransports: ['ws', 'wss']`.
- Customizes `authEndpoint: '/broadcasting/auth'`.
- Injects JWT token header `Authorization: Bearer <token>`.

### 2. Global Broadcasting Hook (`resources/js/hooks/use-broadcasting.js`)
- Subscribes to `private-users.{id}` and `private-agent.feed`.
- Invalidates TanStack Query caches (`queryClient.invalidateQueries`) so UI lists refresh without polling.
- Triggers non-intrusive toast notifications for incoming events.
- Dispatches formatted broadcast records into `useBroadcastStore`.

### 3. Ticket Presence & Collision Hook (`resources/js/hooks/use-ticket-realtime.js`)
- Joins the presence channel `presence-tickets.{ticketId}`.
- Tracks `activeUsers` in real time (`here`, `joining`, `leaving`).
- Identifies other staff members currently viewing the ticket:
  - If another agent is on the ticket, renders an amber **Agent Collision Warning** banner in `TicketDetail.jsx`.
- Automatically appends incoming messages into the conversation feed without full-page reloads.

### 4. Real-Time Broadcast Activity Center (`BroadcastNotificationsDrawer.jsx`)
- Slide-over notification tray accessible from the Navbar bell icon.
- Features:
  - Unread badge counter.
  - Reverb connection health indicator (Live / Connecting / Offline).
  - Event category filter tabs (`All`, `Tickets`, `Replies`, `Assigned`).
  - Expandable JSON raw payload viewer (`<Code />` toggle).
  - Direct deep-linking to tickets (`View Ticket` action).

---

## 5. Local Setup & Testing

### 1. Environment Configuration (`.env`)
```env
BROADCAST_CONNECTION=reverb
QUEUE_CONNECTION=redis

REVERB_APP_ID=980733
REVERB_APP_KEY=mmczeuguguo5yfidtgpn
REVERB_APP_SECRET=3vdxemwdwjhgaxe3hg20
REVERB_HOST="localhost"
REVERB_PORT=8080
REVERB_SCHEME=http

VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"
```

### 2. Running Services Locally

Start the three required background services in separate terminal tabs:

```bash
# Terminal 1: Start Reverb WebSocket Server with debug logging
php artisan reverb:start --debug

# Terminal 2: Start Redis Queue Worker
php artisan queue:listen

# Terminal 3: Start Laravel HTTP Server
php artisan serve --port=8000
```

### 3. Automated Pest Test Suite
Run the broadcasting test suite to verify channel authorization and event broadcasting:

```bash
php artisan test --filter=ReverbBroadcastingTest
```

### 4. Testing via Artisan Command & Tinker
Trigger sample broadcast events manually:

```bash
# Via dedicated test command:
php artisan helpdesk:test-reverb

# Or via Tinker:
php artisan tinker --execute="event(new App\Domain\Ticket\Events\TicketCreated(App\Domain\Ticket\Models\Ticket::first()));"
```

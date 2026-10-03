<?php

declare(strict_types=1);

use App\Domain\Identity\Enums\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Ticket\Models\Ticket;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

// Private personal user channel
Broadcast::channel('users.{id}', function (User $user, int|string $id): bool {
    return (int) $user->id === (int) $id;
}, ['guards' => ['api']]);

// Presence channel for real-time ticket conversation and agent collision detection
Broadcast::channel('tickets.{ticketId}', function (User $user, int|string $ticketId): array|bool {
    $ticket = Ticket::query()->find($ticketId);

    if (! $ticket) {
        return false;
    }

    $isParticipant = (int) $user->id === (int) $ticket->customer_id
        || $user->role === Role::Admin
        || $user->role === Role::Agent;

    if (! $isParticipant) {
        return false;
    }

    return [
        'id' => $user->id,
        'name' => $user->name,
        'email' => $user->email,
        'role' => $user->role->value,
    ];
}, ['guards' => ['api']]);

// Private channel for internal staff discussions on a specific ticket
Broadcast::channel('tickets.{ticketId}.internal', function (User $user, int|string $ticketId): bool {
    $ticket = Ticket::query()->find($ticketId);

    if (! $ticket) {
        return false;
    }

    return $user->role === Role::Admin || $user->role === Role::Agent;
}, ['guards' => ['api']]);

// Private queue & dashboard updates for support agents and administrators
Broadcast::channel('agent.feed', function (User $user): bool {
    return $user->role === Role::Admin || $user->role === Role::Agent;
}, ['guards' => ['api']]);

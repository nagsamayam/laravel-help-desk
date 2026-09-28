<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Role;
use App\Models\Ticket;
use App\Models\User;

final class TicketPolicy
{
    /**
     * Perform pre-authorization checks.
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole(Role::Admin)) {
            return true;
        }

        return null;
    }

    /**
     * Determine whether the user can view any tickets.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the ticket.
     */
    public function view(User $user, Ticket $ticket): bool
    {
        if ($user->hasRole(Role::Agent)) {
            return true;
        }

        return $user->hasRole(Role::Customer) && $ticket->customer_id === $user->id;
    }

    /**
     * Determine whether the user can create tickets.
     */
    public function create(User $user): bool
    {
        return $user->hasRole(Role::Customer);
    }

    /**
     * Determine whether the user can update the ticket.
     */
    public function update(User $user, Ticket $ticket): bool
    {
        return $user->hasRole(Role::Agent);
    }

    /**
     * Determine whether the user can delete the ticket.
     */
    public function delete(User $user, Ticket $ticket): bool
    {
        return false;
    }

    /**
     * Determine whether the user can change status / transition the ticket.
     */
    public function transition(User $user, Ticket $ticket): bool
    {
        return $user->hasRole(Role::Agent);
    }

    /**
     * Determine whether the user can resolve the ticket.
     */
    public function resolve(User $user, Ticket $ticket): bool
    {
        return $user->hasRole(Role::Agent);
    }

    /**
     * Determine whether the user can close the ticket.
     */
    public function close(User $user, Ticket $ticket): bool
    {
        if ($user->hasRole(Role::Agent)) {
            return true;
        }

        return $user->hasRole(Role::Customer) && $ticket->customer_id === $user->id;
    }

    /**
     * Determine whether the user can reopen the ticket.
     */
    public function reopen(User $user, Ticket $ticket): bool
    {
        if ($user->hasRole(Role::Agent)) {
            return true;
        }

        return $user->hasRole(Role::Customer) && $ticket->customer_id === $user->id;
    }

    /**
     * Determine whether the user can assign or reassign the ticket.
     */
    public function assign(User $user, Ticket $ticket): bool
    {
        return $user->hasRole(Role::Agent);
    }

    /**
     * Determine whether the user can evaluate and route the ticket.
     */
    public function route(User $user, Ticket $ticket): bool
    {
        return $user->hasRole(Role::Agent);
    }

    /**
     * Determine whether the user can view messages for the ticket.
     */
    public function viewMessages(User $user, Ticket $ticket): bool
    {
        if ($user->hasRole(Role::Agent)) {
            return true;
        }

        return $user->hasRole(Role::Customer) && $ticket->customer_id === $user->id;
    }

    /**
     * Determine whether the user can add a message to the ticket.
     */
    public function addMessage(User $user, Ticket $ticket): bool
    {
        if ($user->hasRole(Role::Agent)) {
            return true;
        }

        return $user->hasRole(Role::Customer) && $ticket->customer_id === $user->id;
    }

    /**
     * Determine whether the user can add an internal note to the ticket.
     */
    public function addInternalNote(User $user, Ticket $ticket): bool
    {
        return $user->hasRole(Role::Agent);
    }

    /**
     * Determine whether the user can view status history for the ticket.
     */
    public function viewStatusHistory(User $user, Ticket $ticket): bool
    {
        if ($user->hasRole(Role::Agent)) {
            return true;
        }

        return $user->hasRole(Role::Customer) && $ticket->customer_id === $user->id;
    }

    /**
     * Determine whether the user can view audit logs for the ticket.
     */
    public function viewAuditLogs(User $user, Ticket $ticket): bool
    {
        return $user->hasRole(Role::Agent);
    }
}

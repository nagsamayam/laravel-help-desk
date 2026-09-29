<?php

declare(strict_types=1);

namespace App\Domain\Ticket\Policies;

use App\Domain\Identity\Enums\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Ticket\Models\Ticket;
use App\Domain\Ticket\Models\TicketMessage;

final class TicketMessagePolicy
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
     * Determine whether the user can view messages for a ticket.
     */
    public function viewAny(User $user, Ticket $ticket): bool
    {
        if ($user->hasRole(Role::Agent)) {
            return true;
        }

        return $user->hasRole(Role::Customer) && $ticket->customer_id === $user->id;
    }

    /**
     * Determine whether the user can view the specific message.
     */
    public function view(User $user, TicketMessage $message): bool
    {
        if ($user->hasRole(Role::Agent)) {
            return true;
        }

        if ($user->hasRole(Role::Customer)) {
            return $message->ticket->customer_id === $user->id && ! $message->is_internal;
        }

        return false;
    }

    /**
     * Determine whether the user can create a message for the ticket.
     */
    public function create(User $user, Ticket $ticket): bool
    {
        if ($user->hasRole(Role::Agent)) {
            return true;
        }

        return $user->hasRole(Role::Customer) && $ticket->customer_id === $user->id;
    }
}

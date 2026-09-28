<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Role;
use App\Models\Ticket;
use App\Models\TicketStatusHistory;
use App\Models\User;

final class TicketStatusHistoryPolicy
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
     * Determine whether the user can view status history for a ticket.
     */
    public function viewAny(User $user, Ticket $ticket): bool
    {
        if ($user->hasRole(Role::Agent)) {
            return true;
        }

        return $user->hasRole(Role::Customer) && $ticket->customer_id === $user->id;
    }

    /**
     * Determine whether the user can view the specific status history record.
     */
    public function view(User $user, TicketStatusHistory $history): bool
    {
        if ($user->hasRole(Role::Agent)) {
            return true;
        }

        return $user->hasRole(Role::Customer) && $history->ticket->customer_id === $user->id;
    }
}

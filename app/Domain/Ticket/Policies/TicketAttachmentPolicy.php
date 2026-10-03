<?php

declare(strict_types=1);

namespace App\Domain\Ticket\Policies;

use App\Domain\Identity\Enums\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Ticket\Models\TicketAttachment;

final class TicketAttachmentPolicy
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
     * Determine whether the user can view/download the attachment.
     */
    public function view(User $user, TicketAttachment $attachment): bool
    {
        if ($user->hasRole(Role::Agent)) {
            return true;
        }

        if ($user->hasRole(Role::Customer)) {
            if ($attachment->ticket_id !== null) {
                return $attachment->ticket?->customer_id === $user->id;
            }

            return $attachment->user_id === $user->id;
        }

        return false;
    }

    /**
     * Determine whether the user can upload an attachment.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can delete the attachment.
     */
    public function delete(User $user, TicketAttachment $attachment): bool
    {
        if ($user->hasRole(Role::Agent)) {
            return true;
        }

        if ($user->hasRole(Role::Customer)) {
            if ($attachment->ticket_id !== null) {
                return $attachment->ticket?->customer_id === $user->id;
            }

            return $attachment->user_id === $user->id;
        }

        return false;
    }
}

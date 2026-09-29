<?php

declare(strict_types=1);

namespace App\Domain\Ticket\States;

use App\Domain\Ticket\Enums\TicketStatus;
use App\Domain\Ticket\Exceptions\InvalidTicketStateTransitionException;
use App\Domain\Ticket\Models\Ticket;

abstract class TicketState
{
    public function __construct(
        protected readonly Ticket $ticket,
    ) {}

    abstract public function status(): TicketStatus;

    /**
     * @return array<int, TicketStatus>
     */
    abstract public function allowedTransitions(): array;

    public function canTransitionTo(TicketStatus $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    public function transitionTo(TicketStatus $target): Ticket
    {
        if (! $this->canTransitionTo($target)) {
            throw new InvalidTicketStateTransitionException($this->status(), $target);
        }

        $this->ticket->status = $target;
        $this->ticket->save();

        return $this->ticket;
    }

    public function startProgress(): Ticket
    {
        return $this->transitionTo(TicketStatus::InProgess);
    }

    public function waitForCustomer(): Ticket
    {
        return $this->transitionTo(TicketStatus::WaitingForCustomer);
    }

    public function resolve(): Ticket
    {
        return $this->transitionTo(TicketStatus::Resolved);
    }

    public function close(): Ticket
    {
        return $this->transitionTo(TicketStatus::Closed);
    }

    public function reopen(): Ticket
    {
        return $this->transitionTo(TicketStatus::Open);
    }

    public static function for(Ticket $ticket): self
    {
        return match ($ticket->status) {
            TicketStatus::Open => new OpenTicketState($ticket),
            TicketStatus::InProgess => new InProgressTicketState($ticket),
            TicketStatus::WaitingForCustomer => new WaitingForCustomerTicketState($ticket),
            TicketStatus::Resolved => new ResolvedTicketState($ticket),
            TicketStatus::Closed => new ClosedTicketState($ticket),
        };
    }
}

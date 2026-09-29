<?php

declare(strict_types=1);

namespace App\Domain\Ticket\Routing;

use App\Domain\Ticket\Models\Ticket;
use App\Domain\Ticket\Routing\Rules\CategoryRoutingRule;
use App\Domain\Ticket\Routing\Rules\DefaultRoutingRule;
use App\Domain\Ticket\Routing\Rules\TicketRoutingDecision;
use App\Domain\Ticket\Routing\Rules\TicketRoutingRule;
use App\Domain\Ticket\Routing\Rules\UrgentPriorityRoutingRule;
use App\Domain\Ticket\Routing\Rules\VipRoutingRule;

final class TicketRouter
{
    private TicketRoutingRule $firstRule;

    public function __construct(?TicketRoutingRule $firstRule = null)
    {
        $this->firstRule = $firstRule ?? self::createDefaultPipeline();
    }

    public static function createDefault(): self
    {
        return new self(self::createDefaultPipeline());
    }

    /**
     * Builds the standard chain: VIP rule -> urgent rule -> category rule -> default rule.
     */
    public static function createDefaultPipeline(): TicketRoutingRule
    {
        $vipRule = new VipRoutingRule;
        $urgentRule = new UrgentPriorityRoutingRule;
        $categoryRule = new CategoryRoutingRule;
        $defaultRule = new DefaultRoutingRule;

        $vipRule
            ->setNext($urgentRule)
            ->setNext($categoryRule)
            ->setNext($defaultRule);

        return $vipRule;
    }

    /**
     * Set the start of the routing chain.
     */
    public function setFirstRule(TicketRoutingRule $rule): self
    {
        $this->firstRule = $rule;

        return $this;
    }

    /**
     * Route a ticket through the chain of responsibility.
     */
    public function route(Ticket $ticket, bool $persist = false): TicketRoutingDecision
    {
        $decision = $this->firstRule->handle($ticket);

        if ($persist) {
            $decision->apply();
        }

        return $decision;
    }
}

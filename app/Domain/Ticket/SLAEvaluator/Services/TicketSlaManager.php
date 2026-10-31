<?php

declare(strict_types=1);

namespace App\Domain\Ticket\SLAEvaluator\Services;

use App\Domain\Ticket\Models\Ticket;
use App\Domain\Ticket\SLAEvaluator\Evaluators\BaseTicketSlaEvaluator;
use App\Domain\Ticket\SLAEvaluator\Evaluators\PremiumClientDecorator;
use App\Domain\Ticket\SLAEvaluator\Evaluators\UrgentTicketDecorator;

class TicketSlaManager
{
    public function getSlaHours(Ticket $ticket): int
    {
        // 1. Initialize core component
        $evaluator = new BaseTicketSlaEvaluator;

        // 2. Conditionally wrap with decorators
        if ($ticket->is_urgent) {
            $evaluator = new UrgentTicketDecorator($evaluator);
        }

        if ($ticket->client?->is_vip) {
            $evaluator = new PremiumClientDecorator($evaluator);
        }

        // 3. Execute the stacked calculations
        return $evaluator->calculateResolutionWindow($ticket);
    }
}

// Step A: Instantiate the core object
// $pipeline = new BaseTicketSlaEvaluator(); // Returns 48

// Step B: Wrap it in the Urgent Decorator
// $pipeline = new UrgentTicketDecorator($pipeline); // Takes 48 -> returns 24

// Step C: Wrap that wrapper in the Premium Decorator
// $pipeline = new PremiumClientDecorator($pipeline); // Takes 24 -> returns 12

// When you call $pipeline->calculateResolutionWindow($ticket), the execution steps backwards through the stack like an onion:
// 1. PremiumClientDecorator receives the call, but waits. It calls UrgentTicketDecorator.
// 2. UrgentTicketDecorator receives the call, but waits. It calls BaseTicketSlaEvaluator.
// 3. BaseTicketSlaEvaluator executes completely and returns 48.
// 4. UrgentTicketDecorator catches that 48, applies its logic (48 / 2), and passes 24 back up.
// 5. PremiumClientDecorator catches that 24, applies its logic (24 - 12), and returns the final 12.

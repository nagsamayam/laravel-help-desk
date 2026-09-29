<?php

declare(strict_types=1);

namespace App\Domain\Ticket\Routing\Rules;

use App\Domain\Identity\Models\User;
use App\Domain\Ticket\Enums\TicketPriority;
use App\Domain\Ticket\Models\Ticket;
use Closure;
use Illuminate\Support\Str;

final class VipRoutingRule extends TicketRoutingRule
{
    /**
     * @param  array<int, string>  $vipEmailDomains
     * @param  (Closure(User): bool)|null  $vipChecker
     */
    public function __construct(
        private readonly array $vipEmailDomains = ['vip.com', 'enterprise.com', 'partner.org'],
        private readonly ?Closure $vipChecker = null,
        private readonly TicketPriority $targetPriority = TicketPriority::Urgent,
    ) {}

    protected function shouldHandle(Ticket $ticket): bool
    {
        if (Str::contains(Str::lower($ticket->subject), '[vip]')) {
            return true;
        }

        $customer = $ticket->relationLoaded('customer') ? $ticket->customer : $ticket->customer()->first();

        if ($customer === null) {
            return false;
        }

        if ($this->vipChecker !== null) {
            return (bool) ($this->vipChecker)($customer);
        }

        $email = Str::lower($customer->email);
        foreach ($this->vipEmailDomains as $domain) {
            if (Str::endsWith($email, '@'.ltrim($domain, '@'))) {
                return true;
            }
        }

        return false;
    }

    protected function process(Ticket $ticket): TicketRoutingDecision
    {
        return new TicketRoutingDecision(
            ticket: $ticket,
            priority: $this->targetPriority,
            assignedTo: $ticket->assigned_to,
            categoryId: $ticket->category_id,
            matchedRule: 'VIP Rule',
            reason: 'Customer has VIP / Enterprise account status.',
            tags: ['vip', 'high-priority'],
        );
    }
}

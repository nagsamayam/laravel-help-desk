<?php

declare(strict_types=1);

namespace App\Domain\Ticket\Routing\Rules;

use App\Domain\Ticket\Enums\TicketPriority;
use App\Domain\Ticket\Models\Category;
use App\Domain\Ticket\Models\Ticket;
use Illuminate\Support\Str;

final class CategoryRoutingRule extends TicketRoutingRule
{
    /**
     * @param  array<string|int, TicketPriority>  $categoryPriorityMap
     */
    public function __construct(
        private readonly array $categoryPriorityMap = [
            'security' => TicketPriority::High,
            'billing' => TicketPriority::Medium,
            'infrastructure' => TicketPriority::High,
            'hardware' => TicketPriority::Medium,
        ],
    ) {}

    protected function shouldHandle(Ticket $ticket): bool
    {
        return $this->resolveMatchedPriority($ticket) !== null;
    }

    protected function process(Ticket $ticket): TicketRoutingDecision
    {
        $priority = $this->resolveMatchedPriority($ticket) ?? TicketPriority::Medium;
        $categoryName = $this->resolveCategoryName($ticket);

        return new TicketRoutingDecision(
            ticket: $ticket,
            priority: $priority,
            assignedTo: $ticket->assigned_to,
            categoryId: $ticket->category_id,
            matchedRule: 'Category Rule',
            reason: sprintf('Matched routing rule for category [%s].', $categoryName ?? (string) $ticket->category_id),
            tags: ['category', Str::slug($categoryName ?? 'cat-'.$ticket->category_id)],
        );
    }

    private function resolveMatchedPriority(Ticket $ticket): ?TicketPriority
    {
        if (isset($this->categoryPriorityMap[$ticket->category_id])) {
            return $this->categoryPriorityMap[$ticket->category_id];
        }

        $categoryName = $this->resolveCategoryName($ticket);
        if ($categoryName === null) {
            return null;
        }

        $lowerName = Str::lower($categoryName);
        foreach ($this->categoryPriorityMap as $key => $priority) {
            if (is_string($key) && (Str::contains($lowerName, Str::lower($key)) || Str::lower($key) === $lowerName)) {
                return $priority;
            }
        }

        return null;
    }

    private function resolveCategoryName(Ticket $ticket): ?string
    {
        if ($ticket->relationLoaded('category') && $ticket->category !== null) {
            return $ticket->category->name;
        }

        return Category::query()->where('id', $ticket->category_id)->value('name');
    }
}

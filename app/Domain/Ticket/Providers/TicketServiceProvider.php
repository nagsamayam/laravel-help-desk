<?php

declare(strict_types=1);

namespace App\Domain\Ticket\Providers;

use App\Domain\Ticket\Models\Category;
use App\Domain\Ticket\Models\Ticket;
use App\Domain\Ticket\Models\TicketMessage;
use App\Domain\Ticket\Models\TicketStatusHistory;
use App\Domain\Ticket\Policies\CategoryPolicy;
use App\Domain\Ticket\Policies\TicketMessagePolicy;
use App\Domain\Ticket\Policies\TicketPolicy;
use App\Domain\Ticket\Policies\TicketStatusHistoryPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

final class TicketServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap domain services and policies.
     */
    public function boot(): void
    {
        Gate::policy(Ticket::class, TicketPolicy::class);
        Gate::policy(TicketMessage::class, TicketMessagePolicy::class);
        Gate::policy(TicketStatusHistory::class, TicketStatusHistoryPolicy::class);
        Gate::policy(Category::class, CategoryPolicy::class);
    }
}

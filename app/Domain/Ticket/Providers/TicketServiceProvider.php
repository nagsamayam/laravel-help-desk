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
use App\Domain\Ticket\SLA\Contracts\SlaPolicy;
use App\Domain\Ticket\SLA\Policies\DefaultSlaPolicy;
use App\Domain\Ticket\SLA\Policies\VipCustomerSlaDecorator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Override;
use UrgentTicketSlaDecorator;

final class TicketServiceProvider extends ServiceProvider
{
    #[Override]
    public function register()
    {
        $this->app->singleton(SlaPolicy::class, function () {
            return new VipCustomerSlaDecorator(
                new UrgentTicketSlaDecorator(
                    new DefaultSlaPolicy
                )
            );
        });
    }

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

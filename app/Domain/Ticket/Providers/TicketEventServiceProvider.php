<?php

declare(strict_types=1);

namespace App\Domain\Ticket\Providers;

use App\Domain\Ticket\Events\TicketAssigned;
use App\Domain\Ticket\Events\TicketCreated;
use App\Domain\Ticket\Events\TicketMessageAdded;
use App\Domain\Ticket\Events\TicketStatusChanged;
use App\Domain\Ticket\Listeners\LogTicketActivityListener;
use App\Domain\Ticket\Listeners\SendTicketNotificationListener;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

final class TicketEventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the Ticket domain.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected array $listen = [
        TicketCreated::class => [
            LogTicketActivityListener::class,
            SendTicketNotificationListener::class,
        ],
        TicketStatusChanged::class => [
            LogTicketActivityListener::class,
            SendTicketNotificationListener::class,
        ],
        TicketAssigned::class => [
            LogTicketActivityListener::class,
            SendTicketNotificationListener::class,
        ],
        TicketMessageAdded::class => [
            LogTicketActivityListener::class,
            SendTicketNotificationListener::class,
        ],
    ];

    /**
     * Bootstrap domain events and listeners.
     */
    public function boot(): void
    {
        foreach ($this->listen as $event => $listeners) {
            foreach ($listeners as $listener) {
                Event::listen($event, $listener);
            }
        }
    }
}

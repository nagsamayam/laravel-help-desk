<?php

use App\Domain\Audit\Providers\AuditServiceProvider;
use App\Domain\Identity\Providers\IdentityServiceProvider;
use App\Domain\Ticket\Providers\TicketEventServiceProvider;
use App\Domain\Ticket\Providers\TicketServiceProvider;
use App\Infrastructure\Notifications\Providers\NotificationServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    TicketServiceProvider::class,
    TicketEventServiceProvider::class,
    IdentityServiceProvider::class,
    AuditServiceProvider::class,
    NotificationServiceProvider::class,
];

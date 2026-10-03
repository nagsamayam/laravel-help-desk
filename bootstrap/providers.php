<?php

use App\Domain\Audit\Providers\AuditServiceProvider;
use App\Domain\Identity\Providers\IdentityServiceProvider;
use App\Domain\Ticket\Providers\TicketEventServiceProvider;
use App\Domain\Ticket\Providers\TicketServiceProvider;
use App\Infrastructure\Notifications\Providers\NotificationServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\HorizonServiceProvider;

return [
    AuditServiceProvider::class,
    IdentityServiceProvider::class,
    TicketEventServiceProvider::class,
    TicketServiceProvider::class,
    NotificationServiceProvider::class,
    AppServiceProvider::class,
    HorizonServiceProvider::class,
];

<?php

declare(strict_types=1);

namespace App\Domain\Ticket\Repository\Decorators;

use App\Domain\Ticket\Models\Ticket;
use App\Domain\Ticket\Repository\Contracts\TicketRepositoryInterface;
use Illuminate\Contracts\Cache\Repository as Cache;

class CachingTicketRepository implements TicketRepositoryInterface
{
    public function __construct(
        protected TicketRepositoryInterface $repository,
        protected Cache $cache
    ) {}

    public function find(int $id): ?Ticket
    {
        $cacheKey = "tickets.v1.id:{$id}";

        // Intercept the execution layer to add behavior
        return $this->cache->remember($cacheKey, now()->addMinutes(15), function () use ($id) {
            // Forward the call to the underlying repository layer if cache misses
            return $this->repository->find($id);
        });
    }
}

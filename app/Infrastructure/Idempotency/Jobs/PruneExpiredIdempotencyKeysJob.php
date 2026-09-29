<?php

declare(strict_types=1);

namespace App\Infrastructure\Idempotency\Jobs;

use App\Infrastructure\Idempotency\Models\IdempotencyKey;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

final class PruneExpiredIdempotencyKeysJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * @var array<int, int>
     */
    public array $backoff = [60, 180, 300];

    public int $timeout = 120;

    public int $uniqueFor = 1800;

    public function __construct()
    {
        $this->onQueue('maintenance');
    }

    /**
     * The unique ID of the job.
     */
    public function uniqueId(): string
    {
        return 'prune-expired-idempotency-keys';
    }

    /**
     * Get the middleware the job should pass through.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping($this->uniqueId()))->expireAfter(120),
        ];
    }

    /**
     * Execute the job.
     */
    public function handle(): int
    {
        $prunedCount = (new IdempotencyKey)->pruneAll();

        Log::info('Pruned expired idempotency keys', [
            'pruned_count' => $prunedCount,
        ]);

        return $prunedCount;
    }

    /**
     * Handle job failure.
     */
    public function failed(?Throwable $exception): void
    {
        Log::error('PruneExpiredIdempotencyKeysJob failed to execute', [
            'exception' => $exception?->getMessage(),
        ]);
    }
}

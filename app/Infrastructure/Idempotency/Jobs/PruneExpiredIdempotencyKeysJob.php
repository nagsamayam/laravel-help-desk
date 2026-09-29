<?php

declare(strict_types=1);

namespace App\Infrastructure\Idempotency\Jobs;

use App\Infrastructure\Idempotency\Models\IdempotencyKey;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

final class PruneExpiredIdempotencyKeysJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * @var array<int, int>
     */
    public array $backoff = [60, 180, 300];

    public int $timeout = 120;

    public function __construct()
    {
        $this->onQueue('maintenance');
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

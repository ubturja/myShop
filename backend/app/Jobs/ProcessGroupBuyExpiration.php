<?php

namespace App\Jobs;

use App\Services\GroupBuyService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * ProcessGroupBuyExpiration Job
 * 
 * Background job that processes expired group buys.
 * Should be scheduled to run regularly (e.g., every minute).
 * 
 * Responsibilities:
 * - Find all expired group buys
 * - Check member count vs target_size
 * - Update status to FULFILLED or FAILED
 * - Trigger events for downstream processing
 */
class ProcessGroupBuyExpiration implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 120;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(GroupBuyService $groupBuyService): void
    {
        Log::info('ProcessGroupBuyExpiration: Starting job');

        try {
            $stats = $groupBuyService->processExpirations();

            Log::info('ProcessGroupBuyExpiration: Job completed successfully', $stats);
        } catch (\Exception $e) {
            Log::error('ProcessGroupBuyExpiration: Job failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Re-throw to trigger retry mechanism
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessGroupBuyExpiration: Job failed after all retries', [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);

        // TODO: Send alert to monitoring system
    }
}

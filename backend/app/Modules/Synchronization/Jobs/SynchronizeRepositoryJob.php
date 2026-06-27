<?php

namespace App\Modules\Synchronization\Jobs;

use App\Modules\Synchronization\Contracts\GitHubRepositorySyncClientInterface;
use App\Modules\Synchronization\Contracts\SynchronizationRepositoryInterface;
use App\Modules\Synchronization\Exceptions\SynchronizationException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Throwable;

class SynchronizeRepositoryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 840;

    /** @var array<int, int> */
    public array $backoff = [30, 120, 300];

    public function __construct(public readonly int $runId) {}

    /** @return array<int, object> */
    public function middleware(): array
    {
        return [(new WithoutOverlapping("sync-run-{$this->runId}"))->expireAfter(900)];
    }

    public function handle(
        SynchronizationRepositoryInterface $synchronization,
        GitHubRepositorySyncClientInterface $github,
    ): void {
        $context = $synchronization->contextForRun($this->runId);

        if ($context === null || ! in_array($context->run_status, ['queued', 'running'], true)) {
            return;
        }

        if ($context->run_status === 'queued') {
            $synchronization->markRunning($this->runId);
            $context = $synchronization->contextForRun($this->runId);
        }

        try {
            $result = $github->synchronize(
                (int) $context->github_installation_id,
                $context->full_name,
                $context->previous_cursor,
            );
            $synchronization->complete($this->runId, $result);
        } catch (SynchronizationException $exception) {
            if ($exception->retryable && $this->attempts() < $this->tries) {
                throw $exception;
            }

            $status = str_contains($exception->category, 'rate_limit')
                ? 'deferred'
                : 'failed';
            $synchronization->fail(
                $this->runId,
                $exception->category,
                $exception->getMessage(),
                $status,
            );
        }
    }

    public function failed(Throwable $exception): void
    {
        $synchronization = app(SynchronizationRepositoryInterface::class);
        $synchronization->fail(
            $this->runId,
            'retry_exhausted',
            'Synchronization failed after bounded retries.',
        );
    }
}

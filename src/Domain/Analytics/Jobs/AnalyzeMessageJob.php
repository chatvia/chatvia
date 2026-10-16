<?php

declare(strict_types=1);

namespace Domain\Analytics\Jobs;

use Domain\Analytics\Actions\Analysis\AnalyzeMessageAction;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Queued wrapper around {@see AnalyzeMessageAction} for per-message sentiment
 * and tone. Runs on the configured analytics queue.
 */
class AnalyzeMessageJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $externalMessageId)
    {
        // The queue name comes from config, which an attribute cannot read.
        $this->onQueue((string) config('chatvia.analytics.queue', 'analytics'));
    }

    public function handle(AnalyzeMessageAction $action): void
    {
        $action->execute($this->externalMessageId);
    }
}

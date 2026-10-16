<?php

declare(strict_types=1);

namespace Domain\Analytics\Jobs;

use Domain\Analytics\Actions\Analysis\AnalyzeConversationAction;
use Domain\Conversation\Models\Conversation;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\DeleteWhenMissingModels;

/**
 * Queued wrapper around {@see AnalyzeConversationAction} for the scheduled
 * end-of-life conversation analysis. Runs on the configured analytics queue.
 */
// A conversation deleted before the job runs has nothing left to analyze.
#[DeleteWhenMissingModels]
class AnalyzeConversationJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Conversation $conversation)
    {
        // The queue name comes from config, which an attribute cannot read.
        $this->onQueue((string) config('chatvia.analytics.queue', 'analytics'));
    }

    public function handle(AnalyzeConversationAction $action): void
    {
        $action->execute($this->conversation);
    }
}

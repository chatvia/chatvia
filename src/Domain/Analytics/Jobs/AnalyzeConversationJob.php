<?php

declare(strict_types=1);

namespace Domain\Analytics\Jobs;

use Domain\Analytics\Actions\Analysis\AnalyzeConversationAction;
use Domain\Conversation\Models\Conversation;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Queued wrapper around {@see AnalyzeConversationAction} for the scheduled
 * end-of-life conversation analysis. Runs on the configured analytics queue.
 */
class AnalyzeConversationJob implements ShouldQueue
{
    use Queueable;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public Conversation $conversation)
    {
        $this->onQueue((string) config('chatvia.analytics.queue', 'analytics'));
    }

    public function handle(AnalyzeConversationAction $action): void
    {
        $action->execute($this->conversation);
    }
}

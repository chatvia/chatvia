<?php

namespace Domain\Analytics\Jobs;

use Domain\Analytics\Actions\Analysis\AnalyzeConversationAction;
use Domain\Conversation\Models\Conversation;
use Domain\Llm\Usage\AttributesTokenUsage;
use Domain\Llm\Usage\TracksTokenUsage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Queued wrapper around {@see AnalyzeConversationAction} for the scheduled
 * end-of-life conversation analysis. Runs on the configured analytics queue.
 */
class AnalyzeConversationJob implements AttributesTokenUsage, ShouldQueue
{
    use Queueable, TracksTokenUsage;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public Conversation $conversation)
    {
        $this->onQueue((string) config('chatvia.analytics.queue', 'analytics'));
    }

    public function tokenWorkspaceId(): ?int
    {
        return $this->conversation->agent?->user_id;
    }

    public function handle(AnalyzeConversationAction $action): void
    {
        $action->execute($this->conversation);
    }
}

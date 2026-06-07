<?php

namespace Domain\Analytics\Jobs;

use Domain\Analytics\Actions\AnalyzeConversationAction;
use Domain\Conversation\Models\Conversation;
use Domain\Llm\Usage\AttributesTokenUsage;
use Domain\Llm\Usage\TracksTokenUsage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class AnalyzeConversationJob implements AttributesTokenUsage, ShouldQueue
{
    use Queueable, TracksTokenUsage;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public Conversation $conversation)
    {
        $this->onQueue('analytics');
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

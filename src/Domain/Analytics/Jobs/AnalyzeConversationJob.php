<?php

namespace Domain\Analytics\Jobs;

use Domain\Analytics\Actions\AnalyzeConversationAction;
use Domain\Conversation\Models\Conversation;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class AnalyzeConversationJob implements ShouldQueue
{
    use Queueable;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public Conversation $conversation)
    {
        $this->onQueue('analytics');
    }

    public function handle(AnalyzeConversationAction $action): void
    {
        $action->execute($this->conversation);
    }
}

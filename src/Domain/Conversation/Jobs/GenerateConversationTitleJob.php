<?php

namespace Domain\Conversation\Jobs;

use Domain\Agent\Models\Agent;
use Domain\Conversation\Services\Memory\ChatMemory;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GenerateConversationTitleJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Agent $agent,
        public string $externalSessionId,
        public string $conversationId,
    ) {
        $this->onQueue('messages');
    }

    public function handle(ChatMemory $memory): void
    {
        $memory
            ->withContext($this->agent, $this->externalSessionId, $this->conversationId)
            ->updateConversationTitleFromMessages();
    }
}

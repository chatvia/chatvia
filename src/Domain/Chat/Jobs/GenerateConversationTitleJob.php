<?php

namespace Domain\Chat\Jobs;

use Domain\Agents\Models\Agent;
use Domain\Chat\Services\Memory\ChatMemory;
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
            ->setAgent($this->agent)
            ->setExternalSessionId($this->externalSessionId)
            ->setConversationId($this->conversationId)
            ->updateConversationTitleFromMessages();
    }
}

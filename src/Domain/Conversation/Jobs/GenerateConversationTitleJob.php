<?php

namespace Domain\Conversation\Jobs;

use Domain\Agent\Models\Agent;
use Domain\Conversation\Actions\Conversations\GenerateConversationTitleAction;
use Domain\Conversation\Data\Memory\ChatScopeData;
use Domain\Llm\Usage\AttributesTokenUsage;
use Domain\Llm\Usage\TracksTokenUsage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Names a conversation from its first exchange, on the `messages` queue. The
 * title call's tokens are attributed to the agent's workspace.
 */
class GenerateConversationTitleJob implements AttributesTokenUsage, ShouldQueue
{
    use Queueable;
    use TracksTokenUsage;

    public function __construct(
        public Agent $agent,
        public string $externalSessionId,
        public string $conversationId,
    ) {
        $this->onQueue('messages');
    }

    public function handle(GenerateConversationTitleAction $generateTitle): void
    {
        $generateTitle->execute(new ChatScopeData($this->agent, $this->externalSessionId, $this->conversationId));
    }

    public function tokenWorkspaceId(): ?int
    {
        return $this->agent->workspace_id;
    }
}

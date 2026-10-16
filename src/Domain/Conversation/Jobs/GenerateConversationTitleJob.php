<?php

declare(strict_types=1);

namespace Domain\Conversation\Jobs;

use Domain\Agent\Models\Agent;
use Domain\Conversation\Actions\Conversations\GenerateConversationTitleAction;
use Domain\Conversation\Data\Memory\ChatScopeData;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\DeleteWhenMissingModels;
use Illuminate\Queue\Attributes\Queue;

/**
 * Names a conversation from its first exchange, on the `messages` queue. The
 * title call's tokens are attributed to the agent's workspace.
 */
#[Queue('messages')]
// The job is delayed a minute; an agent purged meanwhile needs no title.
#[DeleteWhenMissingModels]
class GenerateConversationTitleJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Agent $agent,
        public readonly string $externalSessionId,
        public readonly string $conversationId,
    ) {}

    public function handle(GenerateConversationTitleAction $generateTitle): void
    {
        $generateTitle->execute(new ChatScopeData($this->agent, $this->externalSessionId, $this->conversationId));
    }
}

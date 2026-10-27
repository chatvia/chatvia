<?php

declare(strict_types=1);

namespace Domain\Conversation\Jobs;

use Domain\Agent\Models\Agent;
use Domain\Conversation\Actions\Messages\StoreAssistantMessageAction;
use Domain\Conversation\Actions\Messages\StoreUserMessageAction;
use Domain\Conversation\Data\Messages\AssistantTurnData;
use Domain\Conversation\Data\Messages\UserTurnData;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\DeleteWhenMissingModels;
use Illuminate\Queue\Attributes\Queue;
use Illuminate\Queue\Attributes\UniqueFor;

/**
 * Writes one chat message to the database off the streaming hot path, on the
 * `messages` queue. The live chat memory already has the message; this makes
 * the chat log, analytics and usage counters catch up. Unique per message id,
 * so a duplicate dispatch never stores a message twice.
 */
#[Queue('messages')]
// Outlasts a backed-up chat queue, yet frees a lock a dead worker left behind.
#[UniqueFor(3600)]
// An agent purged before the job runs has no chat log left to write to.
#[DeleteWhenMissingModels]
class StoreMessageJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Agent $agent,
        public readonly UserTurnData|AssistantTurnData $payload,
    ) {}

    public function handle(StoreUserMessageAction $storeUserMessage, StoreAssistantMessageAction $storeAssistantMessage): void
    {
        if ($this->payload instanceof UserTurnData) {
            $storeUserMessage->execute($this->agent, $this->payload);

            return;
        }

        $storeAssistantMessage->execute($this->agent, $this->payload);
    }

    public function uniqueId(): string
    {
        return "messages:store:id:{$this->payload->external_message_id}";
    }
}

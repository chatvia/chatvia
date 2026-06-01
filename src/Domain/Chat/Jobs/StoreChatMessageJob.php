<?php

namespace Domain\Chat\Jobs;

use Domain\Agents\Models\Agent;
use Domain\Chat\Actions\Messages\StoreChatMessageAction;
use Domain\Chat\Data\Messages\ChatInputPayload;
use Domain\Chat\Data\Messages\ChatOutputPayload;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class StoreChatMessageJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public bool $deleteWhenMissingModels = true;

    public function __construct(
        public Agent $agent,
        public ChatInputPayload|ChatOutputPayload $payload,
    ) {
        $this->onQueue('messages');
    }

    public function handle(StoreChatMessageAction $action): void
    {
        $action->execute($this->agent, $this->payload);
    }

    public function uniqueId(): string
    {
        return 'messages:store:id:'.$this->payload->external_message_id;
    }
}

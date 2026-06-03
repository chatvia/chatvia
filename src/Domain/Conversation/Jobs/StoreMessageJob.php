<?php

namespace Domain\Conversation\Jobs;

use Domain\Agent\Models\Agent;
use Domain\Conversation\Actions\Messages\StoreMessageAction;
use Domain\Conversation\Data\Messages\ChatInputPayload;
use Domain\Conversation\Data\Messages\ChatOutputPayload;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class StoreMessageJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public bool $deleteWhenMissingModels = true;

    public function __construct(
        public Agent $agent,
        public ChatInputPayload|ChatOutputPayload $payload,
    ) {
        $this->onQueue('messages');
    }

    public function handle(StoreMessageAction $action): void
    {
        $action->execute($this->agent, $this->payload);
    }

    public function uniqueId(): string
    {
        return 'messages:store:id:'.$this->payload->external_message_id;
    }
}

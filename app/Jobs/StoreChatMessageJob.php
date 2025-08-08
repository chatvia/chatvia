<?php

namespace App\Jobs;

use App\Data\Chat\ChatInputPayload;
use App\Data\Chat\ChatOutputPayload;
use App\Models\Agent;
use App\Models\Message;
use App\Models\User;
use App\Support\TikToken;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class StoreChatMessageJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public function __construct(
        public Agent $agent,
        public string $role,
        public string $conversationId,
        public string $messageId,
        public ChatInputPayload|ChatOutputPayload $payload
    ) {
        $this->onQueue('messages');
    }

    public function handle(): void
    {
        $isInputMessage = $this->payload instanceof ChatInputPayload;

        Message::create([
            'id' => $this->messageId,
            'conversation_id' => $this->conversationId,
            'agent_id' => $this->agent->id,
            'member_id' => $isInputMessage ? $this->payload->member_id : null,
            'user_id' => $this->agent->user_id,
            'content' => $this->payload->message,
            'role' => $this->role,
            'model' => $this->payload->model,
            'response_time_ms' => $this->payload?->response_time_ms ?? 0,
            'generation_id' => $this->payload?->generation_id ?? null,
            'token_count' => TikToken::countTokens($this->payload->message),
        ]);

        /** @var User $user */
        $user = $this->agent->user;

        if ($isInputMessage) {
            $user->creditSystem()->incrementMessagesUsage($this->agent->user_id);
        }
    }

    public function uniqueId(): string
    {
        return $this->messageId;
    }
}

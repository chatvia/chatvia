<?php

namespace App\Jobs;

use App\Data\Chat\ChatInputPayload;
use App\Data\Chat\ChatOutputPayload;
use App\Models\Agent;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\User;
use App\Services\TikToken;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class StoreChatMessageJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public function __construct(
        public Agent $agent,
        public string $sessionId,
        public string $role,
        public ?string $conversationId,
        public ?string $title,
        public string $messageId,
        public ChatInputPayload|ChatOutputPayload $payload
    ) {
        $this->onQueue('messages');
    }

    public function handle(): void
    {
        $isInputMessage = $this->payload instanceof ChatInputPayload;

        $sessionId = $this->sessionId;
        //        $exp = explode(':', $sessionId);
        //        $type = $exp[1] ?? null;
        //
        //        if (count($exp) === 3) {
        //            $sessionId = $exp[2];
        //        }

        ChatSession::firstOrCreate([
            'id' => $sessionId,
            'agent_id' => $this->agent->id,
            'member_id' => $isInputMessage ? $this->payload->member_id : null,
        ]);

        ChatConversation::firstOrCreate([
            'id' => $this->conversationId,
            'chat_session_id' => $sessionId,
        ], ['title' => $this->title]);

        ChatMessage::create([
            'id' => $this->messageId,
            'chat_conversation_id' => $this->conversationId,
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

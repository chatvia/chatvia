<?php

namespace Domain\Conversation\Actions\Messages;

use Domain\Agent\Models\Agent;
use Domain\Conversation\Data\Messages\UserTurnData;
use Domain\Conversation\Enums\MessageRole;
use Domain\Conversation\Events\SessionLocationUnknown;
use Domain\Conversation\Models\Attachment;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Message;
use Domain\Conversation\Models\Session;
use Domain\Conversation\Services\ChatLog\ChatLogWriter;
use Illuminate\Support\Facades\DB;

/**
 * Writes one visitor message to the chat log. In one transaction it stores the
 * message under its session and conversation, links the files the visitor
 * uploaded for it, and pushes the conversation's retention horizon out. Once
 * committed it asks for the visitor's location and announces the message to
 * analytics. The chatter's monthly count is not kept here: the turn counts
 * the message as it runs ({@see AddMessageToConversationAction}), so an
 * agent's chat log switched off still counts.
 */
class StoreUserMessageAction
{
    public function __construct(
        private readonly ChatLogWriter $chatLog,
    ) {}

    public function execute(Agent $agent, UserTurnData $turn): Message
    {
        $message = DB::transaction(function () use ($agent, $turn): Message {
            $session = $this->chatLog->session($agent, $turn->external_session_id, $turn);
            $conversation = $this->chatLog->conversation($session, $turn->external_conversation_id, $turn->source);

            $message = Message::create($this->attributes($turn, $conversation));

            $this->claimUploadedFiles($conversation, $message);

            // Activity-based retention: a new visitor message pushes the
            // horizon out when it is close (the model only writes then).
            $conversation->extendRetentionIfDueSoon($agent->messageRetentionInDays());

            return $message->setRelation('conversation', $conversation->setRelation('session', $session));
        });

        // Follow-up work reads the committed rows, so it runs after the commit.
        $session = $message->conversation->session;

        $this->requestLocation($session, $turn);
        $this->chatLog->announce($agent, $session, $message, $turn);

        return $message;
    }

    /**
     * @return array<string, mixed>
     */
    private function attributes(UserTurnData $turn, Conversation $conversation): array
    {
        return [
            'external_id' => $turn->external_message_id,
            'workspace_id' => $conversation->workspace_id,
            'chat_conversation_id' => $conversation->id,
            'content' => $turn->message,
            'role' => MessageRole::User,
            'llm_id' => $this->chatLog->llmId($turn->model),
            'is_response_from_qa' => false,
            'source' => $turn->source,
            'has_llm_found_answer' => true,
            // Visitor uploads, so the file chips render again on history reload.
            'metadata' => $turn->attachments !== [] ? ['attachments' => $turn->attachments] : null,
            'created_at' => $turn->sent_at,
            'updated_at' => $turn->sent_at,
        ];
    }

    /**
     * Files uploaded into the conversation and not yet tied to a message
     * belong to this one, so each bubble shows its own file chips.
     */
    private function claimUploadedFiles(Conversation $conversation, Message $message): void
    {
        Attachment::query()
            ->where('chat_conversation_id', $conversation->id)
            ->whereNull('chat_message_id')
            ->update(['chat_message_id' => $message->id]);
    }

    /**
     * Resolve the visitor's location off the hot path. The request-time
     * country (Cloudflare-aware) is the trusted signal; the queued listener
     * may add city and region from the stored IP.
     */
    private function requestLocation(Session $session, UserTurnData $turn): void
    {
        if (! $session->country_id && ($turn->country || $session->ip_address)) {
            SessionLocationUnknown::dispatch($session, $turn->country);
        }
    }
}

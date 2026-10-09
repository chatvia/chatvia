<?php

namespace Domain\Conversation\Services\ChatLog;

use Carbon\CarbonInterface;
use Domain\Agent\Models\Agent;
use Domain\Analytics\Events\MessageSent;
use Domain\Analytics\Services\UserAgentParser;
use Domain\Conversation\Actions\Messages\StoreAssistantMessageAction;
use Domain\Conversation\Actions\Messages\StoreUserMessageAction;
use Domain\Conversation\Data\Messages\AssistantTurnData;
use Domain\Conversation\Data\Messages\UserTurnData;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Message;
use Domain\Conversation\Models\Session;
use Domain\Llm\Models\Llm;

/**
 * The steps every message written to the chat log shares: find or create the
 * visitor session and the conversation the message belongs to, keep their
 * activity stamps fresh, and announce the stored message to analytics. Used
 * by {@see StoreUserMessageAction} and {@see StoreAssistantMessageAction}
 * inside their transactions.
 */
class ChatLogWriter
{
    public function __construct(
        private readonly UserAgentParser $userAgents,
    ) {}

    /**
     * The session the message belongs to. A user turn also supplies what a new
     * session row records about the visitor (IP, user agent, pages, locale)
     * and backfills the signed-in chatter onto a session that has none.
     */
    public function session(Agent $agent, string $sessionExternalId, ?UserTurnData $userTurn = null): Session
    {
        // Keyed on (external_id, agent_id) only — not user_id. The hosted chat
        // creates the session row before it knows the chatter, so keying on
        // user_id would never match that row and the chatter would never land
        // on the session.
        $session = Session::firstOrCreate(
            ['external_id' => $sessionExternalId, 'agent_id' => $agent->id],
            $userTurn !== null ? $this->newSessionAttributes($userTurn) : [],
        );

        $updates = $this->activityStamp($session->last_activity_at);

        // Only fills a missing chatter — never reassigns an existing owner.
        if ($userTurn?->user_id !== null && $session->user_id === null) {
            $updates['user_id'] = $userTurn->user_id;
        }

        if ($updates !== []) {
            $session->update($updates);
        }

        return $session;
    }

    /**
     * The conversation the message belongs to. It normally exists already
     * (created when the conversation was started); when it does not, the
     * surface is derived from the message source — widget messages make a
     * widget conversation, everything else the Hosted Chat surface.
     */
    public function conversation(Session $session, string $conversationExternalId, ?string $messageSource): Conversation
    {
        /** @var Conversation $conversation */
        $conversation = Conversation::firstOrCreate(
            ['external_id' => $conversationExternalId, 'chat_session_id' => $session->id],
            [
                'agent_id' => $session->agent_id,
                'source' => $messageSource === ConversationSource::Widget->value
                    ? ConversationSource::Widget
                    : ConversationSource::HostedChat,
            ],
        );

        $updates = $this->activityStamp($conversation->last_activity_at);

        if (! $conversation->is_visible) {
            $updates['is_visible'] = true;
        }

        if ($updates !== []) {
            $conversation->update($updates);
        }

        return $conversation;
    }

    /** The catalog id of the model a message names, or null when unknown. */
    public function llmId(?string $model): ?int
    {
        return $model ? Llm::catalog()->firstWhere('external_name', $model)?->id : null;
    }

    /**
     * Tell analytics a message was stored. The analytics side works out
     * whether it starts a conversation and buckets it by the session's
     * resolved country, so no separate "conversation started" event is needed.
     */
    public function announce(Agent $agent, Session $session, Message $message, UserTurnData|AssistantTurnData $turn): void
    {
        MessageSent::dispatch(
            $agent->id,
            $agent->user_id,
            $session->user_id,
            $turn->external_conversation_id,
            $turn->external_message_id,
            $turn->external_session_id,
            $message->role->value,
            $turn->model ?: null,
            $session->country?->code,
            $message->response_time_ms,
            $message->prompt_tokens,
            $message->completion_tokens,
            $turn->sent_at,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function newSessionAttributes(UserTurnData $turn): array
    {
        $device = $this->userAgents->detect($turn->user_agent);

        return [
            'user_id' => $turn->user_id,
            'ip_address' => $turn->ip_address,
            'user_agent' => $turn->user_agent,
            'device_type' => $device->deviceType,
            'browser' => $device->browser,
            'os' => $device->os,
            'referrer_url' => $turn->referrer_url,
            'page_url' => $turn->page_url,
            'locale' => $turn->locale ? explode(',', $turn->locale)[0] : null,
        ];
    }

    /**
     * `last_activity_at` is indexed, so every write is a non-HOT update. Bump
     * it at most once a minute.
     *
     * @return array<string, mixed>
     */
    private function activityStamp(?CarbonInterface $lastActivityAt): array
    {
        return $lastActivityAt === null || $lastActivityAt->lt(now()->subMinute())
            ? ['last_activity_at' => now()]
            : [];
    }
}

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
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;
use Support\Localization\Country\Models\Country;

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
        // Keyed on (external_id, agent_id) only — not chatter_id. The hosted
        // chat creates the session row before it knows the chatter, so keying
        // on chatter_id would never match that row and the chatter would never
        // land on the session.
        $session = Session::firstOrCreate(
            ['external_id' => $sessionExternalId, 'agent_id' => $agent->id],
            [
                'workspace_id' => $agent->workspace_id,
                ...($userTurn instanceof UserTurnData ? $this->newSessionAttributes($userTurn) : []),
            ],
        );

        $updates = $this->isStale($session->last_activity_at) ? ['last_activity_at' => now()] : [];

        // Only fills a missing chatter — never reassigns an existing owner, and
        // never on a staff session (a session has at most one viewer).
        if ($userTurn?->chatter_id !== null && $session->chatter_id === null && $session->user_id === null) {
            $updates['chatter_id'] = $userTurn->chatter_id;
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
     *
     * A new message moves the conversation in the chat log, which is ordered
     * by `updated_at`. The stamp is throttled on `updated_at` as well as
     * `last_activity_at`: the widget heartbeat keeps `last_activity_at` fresh
     * without touching `updated_at`, so a throttle on it alone would often
     * leave the conversation where it was.
     */
    public function conversation(Session $session, string $conversationExternalId, ?string $messageSource): Conversation
    {
        $conversation = $this->findOrCreateConversation($session, $conversationExternalId, $messageSource);

        $updates = $this->isStale($conversation->updated_at) || $this->isStale($conversation->last_activity_at)
            ? ['last_activity_at' => now(), 'updated_at' => now()]
            : [];

        // The first message makes a hidden conversation visible and gives it
        // the full retention window (it was started with a short one).
        if (! $conversation->is_visible) {
            $updates = [...$updates, ...Conversation::visibleAttributes()];
        }

        if ($updates !== []) {
            $conversation->forceFill($updates)->save();
        }

        return $conversation;
    }

    /**
     * One lookup that also sees a soft-deleted row, so the common case (the
     * conversation exists) costs a single query.
     */
    private function findOrCreateConversation(Session $session, string $conversationExternalId, ?string $messageSource): Conversation
    {
        $conversation = Conversation::withTrashed()
            ->where('external_id', $conversationExternalId)
            ->where('chat_session_id', $session->id)
            ->first();

        if ($conversation instanceof Conversation && $conversation->trashed()) {
            $this->reviveExpiredEmptyConversation($conversation);
        }

        return $conversation ?? Conversation::createOrFirst(
            ['external_id' => $conversationExternalId, 'chat_session_id' => $session->id],
            [
                'workspace_id' => $session->workspace_id,
                'agent_id' => $session->agent_id,
                'source' => $messageSource === ConversationSource::Widget->value
                    ? ConversationSource::Widget
                    : ConversationSource::HostedChat,
            ],
        );
    }

    /**
     * A widget left open for over a day: its empty conversation expired (a
     * hidden conversation lives one day) while the page kept its id. The
     * visitor's first message brings it back instead of failing. A
     * conversation someone deleted, or one that had messages, stays gone: the
     * message is refused as not found rather than reusing its id.
     */
    private function reviveExpiredEmptyConversation(Conversation $trashed): void
    {
        if ($trashed->is_visible || $trashed->deleted_by !== null) {
            throw (new ModelNotFoundException)->setModel(Conversation::class, [$trashed->external_id]);
        }

        $trashed->restore();
    }

    /**
     * Tell analytics a message was stored. The analytics side works out
     * whether it starts a conversation and buckets it by the session's
     * resolved country, so no separate "conversation started" event is needed.
     * A reply also carries whether it found an answer and its confidence, so
     * the satisfaction numbers read the rollup instead of the chat log.
     */
    public function announce(Agent $agent, Session $session, Message $message, UserTurnData|AssistantTurnData $turn): void
    {
        MessageSent::dispatch(
            $agent->id,
            $agent->workspace_id,
            $session->chatter_id,
            $turn->external_conversation_id,
            $turn->external_message_id,
            $turn->external_session_id,
            $message->role->value,
            $turn->llm_id,
            // From the cached country table: no query per stored message.
            Country::codeFor($session->country_id),
            $message->response_time_ms,
            $message->prompt_tokens,
            $message->completion_tokens,
            $turn->sent_at,
            $message->has_llm_found_answer,
            $message->confidence_score,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function newSessionAttributes(UserTurnData $turn): array
    {
        $device = $this->userAgents->detect($turn->user_agent);

        return [
            'chatter_id' => $turn->chatter_id,
            'ip_address' => $turn->ip_address,
            'user_agent' => $turn->user_agent,
            'device_type' => $device->deviceType,
            'browser' => $device->browser,
            'os' => $device->os,
            'referrer_url' => $turn->referrer_url,
            'page_url' => $turn->page_url,
            'locale' => $turn->locale ? Str::before($turn->locale, ',') : null,
        ];
    }

    /**
     * True when a stamp is missing or more than a minute old. The session and
     * conversation stamps move at most once a minute, so a quick exchange does
     * not rewrite the rows on every message.
     */
    private function isStale(?CarbonInterface $stamp): bool
    {
        return ! $stamp instanceof CarbonInterface || $stamp->lt(now()->subMinute());
    }
}

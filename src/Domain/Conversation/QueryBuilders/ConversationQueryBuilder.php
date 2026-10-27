<?php

namespace Domain\Conversation\QueryBuilders;

use DateTimeImmutable;
use Domain\Conversation\Data\Memory\ChatViewerData;
use Domain\Conversation\Enums\ConfidenceBand;
use Domain\Conversation\Enums\HandoffStatus;
use Domain\Conversation\Enums\MessageFeedback;
use Domain\Conversation\Enums\MessageOrigin;
use Domain\Conversation\Enums\MessageRole;
use Domain\Conversation\Enums\UnansweredFilter;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Message;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\Cursor;
use Illuminate\Support\Collection;

/**
 * Query scopes for conversations: the chat-log filters and the lookups that
 * keep a conversation inside the session it belongs to.
 *
 * @template TModel of Conversation
 *
 * @extends Builder<TModel>
 */
class ConversationQueryBuilder extends Builder
{
    /** A visitor counts as online while their conversation was active this recently. */
    public const int ONLINE_WINDOW_MINUTES = 3;

    /** The columns of the latest message {@see withLastMessage()} reads: what the lists show. */
    private const array LAST_MESSAGE_COLUMNS = ['id', 'content', 'role', 'created_at'];

    /** @return self<TModel> */
    public function forAgent(int $agentId): self
    {
        return $this->where('agent_id', $agentId);
    }

    /**
     * Only conversations of one visitor session of one agent. Lookups by a
     * client-supplied conversation id go through this, so an id from another
     * session or agent never matches.
     *
     * @return self<TModel>
     */
    public function inSession(string $sessionExternalId, int $agentId): self
    {
        return $this->whereHas('session', static function (Builder $query) use ($sessionExternalId, $agentId): void {
            $query->where('external_id', $sessionExternalId)->where('agent_id', $agentId);
        });
    }

    /**
     * Conversations a widget visitor may still reach: in a session of this
     * agent (and, on a protected agent, of the signed-in chatter), and not
     * cleared by the visitor. A cleared thread stays in the workspace's chat
     * log but never comes back on the widget, even when its id is replayed.
     * Every widget lookup by a client-supplied conversation id applies this.
     *
     * @return self<TModel>
     */
    public function reachableByWidgetVisitor(int $agentId, ?ChatViewerData $viewer = null): self
    {
        return $this->whereNull('cleared_at')
            ->whereHas('session', static function (Builder $session) use ($agentId, $viewer): void {
                $session->where('agent_id', $agentId);

                if ($viewer instanceof ChatViewerData) {
                    $session->where($viewer->sessionColumn(), $viewer->id);
                }
            });
    }

    /** @return self<TModel> */
    public function withSessionAndTopic(): self
    {
        return $this->with(['session.country', 'topics']);
    }

    /**
     * Loads each conversation's latest message (text, role and time) as the
     * `lastMessage` relation for a list. A LATERAL subquery reads one message
     * per conversation, newest first, as a backward scan of the
     * (chat_conversation_id, created_at, id) index. Eager-loading
     * `lastMessage` (latestOfMany) would read every message of every listed
     * conversation to find the newest.
     *
     * @return self<TModel>
     */
    public function withLastMessage(): self
    {
        if ($this->getQuery()->columns === null) {
            $this->select($this->qualifyColumn('*'));
        }

        $latest = $this->getQuery()->newQuery()
            ->from('chat_messages')
            ->select(array_map(
                static fn (string $column): string => "chat_messages.{$column} as last_message_{$column}",
                self::LAST_MESSAGE_COLUMNS,
            ))
            ->whereColumn('chat_messages.chat_conversation_id', $this->qualifyColumn('id'))
            ->orderByDesc('chat_messages.created_at')
            ->orderByDesc('chat_messages.id')
            ->limit(1);

        return $this->leftJoinLateral($latest, 'last_message')
            ->addSelect(array_map(static fn (string $column): string => "last_message.last_message_{$column}", self::LAST_MESSAGE_COLUMNS))
            ->afterQuery(static function (Collection $results): void {
                $results->whereInstanceOf(Conversation::class)
                    ->each(static fn (Conversation $conversation) => self::moveLastMessageToRelation($conversation));
            });
    }

    /**
     * Turns the `last_message_*` columns of {@see withLastMessage()} into the
     * `lastMessage` relation (null for a conversation without messages), and
     * takes them off the conversation's own attributes.
     */
    private static function moveLastMessageToRelation(Conversation $conversation): void
    {
        $attributes = $conversation->getAttributes();
        $message = [];

        foreach (self::LAST_MESSAGE_COLUMNS as $column) {
            $message[$column] = $attributes["last_message_{$column}"] ?? null;
            unset($attributes["last_message_{$column}"]);
        }

        $conversation->setRawAttributes($attributes, true);
        $conversation->setRelation('lastMessage', $message['id'] === null
            ? null
            : new Message()->newFromBuilder([...$message, 'chat_conversation_id' => $conversation->getKey()]));
    }

    /** @return self<TModel> */
    public function filterByTopic(?string $topicId): self
    {
        return $this->when($topicId, fn ($query) => $query->whereHas('topics', fn ($q) => $q->where('topics.id', $topicId)));
    }

    /** @return self<TModel> */
    public function filterBySentiment(?string $sentiment): self
    {
        // Conversation-level sentiment is stamped by the consolidated analysis
        // pass onto `chat_conversations.sentiment` — the same column the chat-log
        // UI renders (see ChatLogConversationData). Filter on the conversation
        // row, not per-message sentiment, so the filter matches what's shown.
        return $this->when($sentiment, fn ($query) => $query->where('sentiment', $sentiment));
    }

    /** @return self<TModel> */
    public function filterByTone(?string $tone): self
    {
        return $this->when($tone, fn ($query) => $query->where('tone', $tone));
    }

    /** @return self<TModel> */
    public function filterByDateRange(?string $startDate, ?string $endDate): self
    {
        return $this->when($startDate && $endDate, function ($query) use ($startDate, $endDate): void {
            $query->whereBetween('created_at', ["{$startDate} 00:00:00", "{$endDate} 23:59:59"]);
        });
    }

    /** @return self<TModel> */
    public function filterByCountry(?string $countryId): self
    {
        return $this->when($countryId, function ($query) use ($countryId): void {
            $query->whereHas('session', fn ($q) => $q->where('country_id', $countryId));
        });
    }

    /** @return self<TModel> */
    public function filterByConfidenceScore(?string $confidenceScore): self
    {
        return $this->when($confidenceScore, function ($query) use ($confidenceScore): void {
            $query->whereHas('messages', function ($q) use ($confidenceScore): void {
                // The predicate of messages_confidence_idx, written out so the
                // planner can use that index for the EXISTS.
                $q->where('role', MessageRole::Assistant)->whereNotNull('confidence_score');
                match (ConfidenceBand::tryFrom($confidenceScore)) {
                    ConfidenceBand::High => $q->where('confidence_score', '>=', ConfidenceBand::HIGH_FLOOR),
                    // The high floor itself is high, so a score is in one band only.
                    ConfidenceBand::Medium => $q->where('confidence_score', '>=', ConfidenceBand::MEDIUM_FLOOR)->where('confidence_score', '<', ConfidenceBand::HIGH_FLOOR),
                    ConfidenceBand::Low => $q->where('confidence_score', '<', ConfidenceBand::MEDIUM_FLOOR),
                    null => null,
                };
            });
        });
    }

    /** @return self<TModel> */
    public function filterByFeedback(?string $feedback): self
    {
        return $this->when($feedback, function ($query) use ($feedback): void {
            $query->whereHas('messages', function ($q) use ($feedback): void {
                $q->where('role', MessageRole::Assistant)
                    ->whereNotNull('feedback')
                    ->where('feedback', $feedback);
            });
        });
    }

    /**
     * Conversations with a reply that still needs a look: no knowledge was
     * found for it (no Q&A match and no passage passed the relevance check),
     * or, for {@see UnansweredFilter::NeedsLook}, a visitor gave it a
     * thumbs-down. A reply staff already revised no longer counts, so a
     * conversation leaves the filter once its gaps are filled. Playground
     * tests are left out.
     *
     * @return self<TModel>
     */
    public function filterByUnanswered(?string $unanswered): self
    {
        $filter = UnansweredFilter::tryFrom($unanswered ?? '');

        return $this->when($filter, function ($query) use ($filter): void {
            $query->whereHas('messages', function (Builder $q) use ($filter): void {
                $q->where('role', MessageRole::Assistant)
                    ->whereNull('agent_question_answer_id')
                    ->where(static function (Builder $origin): void {
                        $origin->whereNull('source')->orWhere('source', '!=', MessageOrigin::Playground->value);
                    })
                    ->where(static function (Builder $reason) use ($filter): void {
                        $reason->where(static fn (Builder $notFound) => $notFound->where('has_llm_found_answer', false)->where('is_response_from_qa', false));
                        if ($filter === UnansweredFilter::NeedsLook) {
                            $reason->orWhere('feedback', MessageFeedback::Negative->value);
                        }
                    });
            });
        });
    }

    /** @return self<TModel> */
    public function filterBySource(?string $source): self
    {
        // Each conversation carries its originating surface on the
        // `chat_conversations.source` enum (hosted_chat / widget / bar). A
        // simple equality filter — no message-level whereHas needed.
        return $this->when($source, fn ($query) => $query->where('source', $source));
    }

    /** @return self<TModel> */
    public function filterByOnlineStatus(?bool $onlineOnly): self
    {
        return $this->when($onlineOnly, function ($query): void {
            $query->where('last_activity_at', '>=', now()->subMinutes(self::ONLINE_WINDOW_MINUTES));
        });
    }

    /**
     * What the Inbox lists: the visitor was active inside the online window,
     * the chat waits for a human (asked within the waiting window, so a
     * request nobody answered does not stay forever), or this operator holds it.
     *
     * @return self<TModel>
     */
    public function inInboxOf(int $operatorId, int $waitingWindowHours): self
    {
        return $this->where(function ($query) use ($operatorId, $waitingWindowHours): void {
            $query->where('last_activity_at', '>=', now()->subMinutes(self::ONLINE_WINDOW_MINUTES))
                ->orWhere(function ($waiting) use ($waitingWindowHours): void {
                    $waiting->where('handoff_status', HandoffStatus::Pending)
                        ->where('last_activity_at', '>=', now()->subHours($waitingWindowHours));
                })
                ->orWhere(function ($held) use ($operatorId): void {
                    $held->where('handoff_status', HandoffStatus::Human)->where('handoff_user_id', $operatorId);
                });
        });
    }

    /**
     * Newest activity first. `id` breaks ties, so the order is total and a
     * keyset page never skips or repeats a row.
     *
     * @return self<TModel>
     */
    public function orderByLatestActivity(): self
    {
        return $this->orderByDesc('updated_at')->orderByDesc('id');
    }

    /**
     * One keyset page, newest activity first. With the agent and visible
     * filters, conversations_agent_visible_idx (agent_id, updated_at, id)
     * serves both the order and the cursor bound, so a deep page costs the
     * same as the first (no OFFSET, no COUNT). A cursor that is not one this
     * list handed out starts again at the first page instead of failing.
     *
     * @return CursorPaginator<int, TModel>
     */
    public function latestActivityPage(int $perPage, ?string $cursor): CursorPaginator
    {
        $decoded = $this->latestActivityCursor($cursor);

        // The paginator's bound is `updated_at < ? OR (updated_at = ? AND
        // id < ?)`, which the planner cannot turn into an index range; the
        // redundant `updated_at <= ?` can, so a deep page does not walk the
        // index from the top. An empty string, not null, means "first page":
        // with null the paginator would read a `cursor` from the request.
        return $this->orderByLatestActivity()
            ->when($decoded, fn (self $query, Cursor $at): self => $query->where('updated_at', '<=', $at->parameter('updated_at')))
            ->cursorPaginate($perPage, cursor: $decoded ?? '');
    }

    /** @return self<TModel> */
    public function onlyVisible(): self
    {
        return $this->where('is_visible', true);
    }

    /**
     * The decoded "next page" cursor of {@see latestActivityPage()}, or null
     * when it is missing or does not carry a valid `updated_at` and `id`.
     */
    private function latestActivityCursor(?string $encoded): ?Cursor
    {
        $cursor = Cursor::fromEncoded($encoded);

        if (! $cursor instanceof Cursor || ! $cursor->pointsToNextItems()) {
            return null;
        }

        $parameters = $cursor->toArray();
        $updatedAt = $parameters['updated_at'] ?? null;

        return is_int($parameters['id'] ?? null) && is_scalar($updatedAt) && $this->isTimestamp((string) $updatedAt) ? $cursor : null;
    }

    /** True for a `Y-m-d H:i:s` string that names a real moment (no overflowing fields). */
    private function isTimestamp(string $value): bool
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value);

        return $parsed instanceof DateTimeImmutable && $parsed->format('Y-m-d H:i:s') === $value;
    }
}

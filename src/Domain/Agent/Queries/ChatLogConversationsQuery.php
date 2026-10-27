<?php

namespace Domain\Agent\Queries;

use Domain\Agent\Data\ChatLog\ChatLogFiltersData;
use Domain\Agent\Data\Resources\ChatLog\Conversations\ChatLogConversationData;
use Domain\Agent\Data\Resources\ChatLog\Conversations\ChatLogConversationsResourceData;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Message;
use Domain\Form\Models\FormSubmission;
use Illuminate\Support\Collection;
use Support\License\Entitlements;

/**
 * One page of an agent's chat log, newest activity first, narrowed by the
 * dashboard's filters. Keyset-paged: the dashboard's "Load more" sends back the
 * page's `next_cursor`, so there is no total count and no OFFSET scan. Each
 * row carries its first message as an excerpt, and the page lists which of its
 * conversations captured a lead (a submitted form); none without a license
 * key, since leads need one.
 */
class ChatLogConversationsQuery
{
    private const int PER_PAGE = 10;

    public function __construct(private readonly Entitlements $entitlements) {}

    public function get(Agent $agent, ChatLogFiltersData $filters): ChatLogConversationsResourceData
    {
        $page = Conversation::query()
            ->forAgent($agent->id)
            ->onlyVisible()
            ->withSessionAndTopic()
            ->filterByTopic($filters->topic)
            ->filterBySentiment($filters->sentiment)
            ->filterByTone($filters->tone)
            ->filterByDateRange($filters->startDate, $filters->endDate)
            ->filterByCountry($filters->country)
            ->filterByConfidenceScore($filters->confidenceScore)
            ->filterByFeedback($filters->feedback)
            ->filterBySource($filters->source)
            ->filterByUnanswered($filters->unanswered)
            ->filterByOnlineStatus($filters->onlineOnly)
            ->withCount('messages')
            ->latestActivityPage(self::PER_PAGE, $filters->cursor);

        /** @var Collection<int, Conversation> $conversations */
        $conversations = collect($page->items());
        $conversationIds = $conversations->pluck('id');
        $excerpts = Message::query()->firstExcerptsForConversations($conversationIds);

        return new ChatLogConversationsResourceData(
            conversations: $conversations
                ->map(fn (Conversation $conversation): ChatLogConversationData => ChatLogConversationData::fromConversation(
                    $conversation,
                    $excerpts->get($conversation->id),
                ))
                ->values(),
            // Read from the models: the cursor holds their (updated_at, id).
            next_cursor: $page->nextCursor()?->encode(),
            lead_conversation_ids: $this->leadConversationIds($agent, $conversationIds),
        );
    }

    /**
     * The internal ids of the conversations ON THIS PAGE that have a SUBMITTED
     * form — the list only marks rows it shows, so there is no need to load
     * every lead the agent ever captured. A pending row (a form rendered but
     * never filled) is not a lead.
     *
     * @param  Collection<int, int>  $conversationIds
     * @return Collection<int, int>
     */
    private function leadConversationIds(Agent $agent, Collection $conversationIds): Collection
    {
        if ($conversationIds->isEmpty() || ! $this->entitlements->capabilities()) {
            return collect();
        }

        return FormSubmission::query()
            ->where('agent_id', $agent->id)
            ->submitted()
            ->whereIn('chat_conversation_id', $conversationIds)
            ->distinct()
            ->pluck('chat_conversation_id');
    }
}

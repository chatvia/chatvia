<?php

namespace Domain\Agent\Actions\ChatLog;

use Domain\Agent\Data\ChatLog\ChatLogFiltersData;
use Domain\Agent\Data\Resources\ChatLog\Conversations\ChatLogConversationData;
use Domain\Agent\Data\Resources\ChatLog\Conversations\ChatLogConversationsResourceData;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Message;
use Domain\Form\Models\FormSubmission;
use Illuminate\Support\Collection;

/**
 * One page of an agent's chat log, newest activity first, narrowed by the
 * dashboard's filters. Each row carries its first message as an excerpt, and
 * the page lists which of its conversations captured a lead (a submitted
 * form).
 */
class ListChatLogConversationsAction
{
    private const int PER_PAGE = 10;

    public function execute(Agent $agent, ChatLogFiltersData $filters): ChatLogConversationsResourceData
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
            ->filterByOnlineStatus($filters->onlineOnly)
            ->withCount('messages')
            ->orderByLatestActivity()
            ->paginate(self::PER_PAGE, pageName: 'conversations', page: $filters->page);

        $conversationIds = $page->pluck('id');
        $excerpts = Message::query()->firstExcerptsForConversations($conversationIds);

        $page->through(fn (Conversation $conversation): ChatLogConversationData => ChatLogConversationData::fromConversation(
            $conversation,
            $excerpts->get($conversation->id),
        ));

        return new ChatLogConversationsResourceData(
            conversations: $page,
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
        if ($conversationIds->isEmpty()) {
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

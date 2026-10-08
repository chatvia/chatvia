<?php

namespace Domain\Agent\Data\Resources\ChatLog\Conversations;

use Domain\Agent\Models\Agent;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Message;
use Domain\Form\Models\FormSubmission;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class ChatLogConversationsResourceData extends Data
{
    /**
     * @param  Collection<int, int>  $lead_conversation_ids  conversation
     *                                                       IDs (PK on chat_conversations) — kept as int to match the source
     *                                                       query in `getLeadConversationIds()`.
     */
    public function __construct(
        public LengthAwarePaginator $conversations,
        public Collection $lead_conversation_ids,
    ) {}

    public static function fromAgent(
        Agent $agent,
        int $page = 1,
        ?string $topicFilter = null,
        ?string $sentimentFilter = null,
        ?string $toneFilter = null,
        ?string $startDate = null,
        ?string $endDate = null,
        ?string $countryFilter = null,
        ?string $confidenceScoreFilter = null,
        ?string $feedbackFilter = null,
        ?string $sourceFilter = null,
        ?bool $onlineOnly = null,
    ): self {
        $conversationsPaginator = Conversation::query()
            ->forAgent($agent->id)
            ->onlyVisible()
            ->withSessionAndTopic()
            ->filterByTopic($topicFilter)
            ->filterBySentiment($sentimentFilter)
            ->filterByTone($toneFilter)
            ->filterByDateRange($startDate, $endDate)
            ->filterByCountry($countryFilter)
            ->filterByConfidenceScore($confidenceScoreFilter)
            ->filterByFeedback($feedbackFilter)
            ->filterBySource($sourceFilter)
            ->filterByOnlineStatus($onlineOnly)
            ->withCount('messages')
            ->orderByLatestActivity()
            ->paginate(10, pageName: 'conversations', page: $page);

        $conversationIds = $conversationsPaginator->pluck('id');
        $excerpts = self::getFirstMessageExcerpts($conversationIds);

        $conversationsPaginator->through(
            fn (Conversation $conversation): ChatLogConversationData => ChatLogConversationData::fromConversation(
                $conversation,
                $excerpts->get($conversation->id)
            )
        );

        $leadConversationIds = self::getLeadConversationIds($agent, $conversationIds);

        return new self(
            conversations: $conversationsPaginator,
            lead_conversation_ids: $leadConversationIds,
        );
    }

    /**
     * @return Collection<int, string>
     */
    private static function getFirstMessageExcerpts(Collection $conversationIds): Collection
    {
        return Message::query()->firstExcerptsForConversations($conversationIds);
    }

    /**
     * @param  Collection<int, int>  $conversationIds  the conversations on the current page
     * @return Collection<int, int>
     */
    private static function getLeadConversationIds(Agent $agent, Collection $conversationIds): Collection
    {
        if ($conversationIds->isEmpty()) {
            return collect();
        }

        // The internal chat_conversation_id of each conversation ON THIS PAGE that
        // has a SUBMITTED form. The list only marks rows the page shows, so there
        // is no need to load every lead the agent ever captured. agent_id pins the
        // row to this agent. Pending rows (a form rendered but never filled) are
        // not leads, so they're excluded.
        return FormSubmission::query()
            ->where('agent_id', $agent->id)
            ->where('status', FormSubmission::STATUS_SUBMITTED)
            ->whereIn('chat_conversation_id', $conversationIds)
            ->distinct()
            ->pluck('chat_conversation_id');
    }
}

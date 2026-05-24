<?php

namespace Domain\Agents\Data\Resources\ChatLog;

use Domain\Agents\Models\Agent;
use Domain\Chat\Models\ChatConversation;
use Domain\Chat\Models\ChatMessage;
use Domain\Agents\Models\Lead;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class ChatLogConversationsResourceData extends Data
{
    /**
     * @param  Collection<int, string>  $lead_conversation_ids
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
        $conversationsPaginator = ChatConversation::query()
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
            fn (ChatConversation $conversation) => ChatLogConversationData::fromConversation(
                $conversation,
                $excerpts->get($conversation->id)
            )
        );

        $leadConversationIds = self::getLeadConversationIds($agent);

        return new self(
            conversations: $conversationsPaginator,
            lead_conversation_ids: $leadConversationIds,
        );
    }

    /**
     * @return Collection<string, string>
     */
    private static function getFirstMessageExcerpts(Collection $conversationIds): Collection
    {
        if ($conversationIds->isEmpty()) {
            return collect();
        }

        $firstMessagesRaw = ChatMessage::query()
            ->whereIn('chat_conversation_id', $conversationIds)
            ->orderBy('created_at')
            ->get(['chat_conversation_id', 'content', 'created_at']);

        $firstPerConversation = collect();
        foreach ($firstMessagesRaw as $message) {
            if (! $firstPerConversation->has($message->chat_conversation_id)) {
                $firstPerConversation->put(
                    $message->chat_conversation_id,
                    str($message->content)->stripTags()->limit(80)->value()
                );
            }
        }

        return $firstPerConversation;
    }

    private static function getLeadConversationIds(Agent $agent): Collection
    {
        $allConversationIds = ChatConversation::query()
            ->forAgent($agent->id)
            ->pluck('id');

        return Lead::query()
            ->where('agent_id', $agent->id)
            ->whereIn('chat_conversation_id', $allConversationIds)
            ->pluck('chat_conversation_id');
    }
}

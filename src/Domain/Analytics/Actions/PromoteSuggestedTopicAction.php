<?php

namespace Domain\Analytics\Actions;

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Analytics\Events\ConversationTopicAssigned;
use Domain\Conversation\Topic\Actions\CreateAgentTopicAction;
use Domain\Conversation\Topic\Models\ConversationTopic;
use Domain\Conversation\Topic\Models\Topic;
use Illuminate\Support\Facades\DB;
use Support\Exceptions\DomainValidationException;

/**
 * Promote an emergent topic suggestion into the agent's real taxonomy: create
 * the topic (reusing CreateAgentTopicAction), then convert every suggestion row
 * carrying that label on the agent's conversations into a committed topic and
 * roll it into analytics_agent_topic_daily (one event per newly-committed
 * conversation, bucketed by the conversation's own day).
 */
class PromoteSuggestedTopicAction
{
    public function __construct(
        private readonly CreateAgentTopicAction $createAgentTopic,
    ) {}

    /**
     * @throws DomainValidationException when the agent already has a topic with this name
     */
    public function execute(Agent $agent, User $user, string $label): Topic
    {
        /** @var array{0: Topic, 1: list<\DateTimeInterface>} $result */
        $result = DB::transaction(function () use ($agent, $user, $label): array {
            $topic = $this->createAgentTopic->execute($agent, $user, $label, '');

            $rows = ConversationTopic::query()
                ->where('suggested_label', $label)
                ->whereHas('conversation.session', fn ($query) => $query->where('agent_id', $agent->id))
                ->with('conversation:id,created_at')
                ->get();

            $occurredAt = [];

            foreach ($rows as $row) {
                $conversation = $row->conversation;
                $row->delete();

                if ($conversation === null) {
                    continue;
                }

                $committed = ConversationTopic::firstOrCreate([
                    'chat_conversation_id' => $conversation->id,
                    'topic_id' => $topic->id,
                ]);

                if ($committed->wasRecentlyCreated) {
                    $occurredAt[] = $conversation->created_at;
                }
            }

            return [$topic, $occurredAt];
        });

        [$topic, $occurredAt] = $result;

        // After commit: roll the now-committed topic into the daily analytics.
        foreach ($occurredAt as $createdAt) {
            ConversationTopicAssigned::dispatch($agent->id, $agent->user_id, $topic->id, $createdAt);
        }

        return $topic;
    }
}

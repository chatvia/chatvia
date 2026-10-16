<?php

use Domain\Agent\Models\Agent;
use Domain\Analytics\Actions\Topics\PromoteSuggestedTopicAction;
use Domain\Analytics\Events\ConversationTopicAssigned;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Topic\Actions\CreateAgentTopicAction;
use Domain\Conversation\Topic\Models\ConversationTopic;
use Domain\Conversation\Topic\Models\Topic;
use Illuminate\Support\Facades\Event;
use Support\Exceptions\DomainValidationException;

/**
 * PromoteSuggestedTopicAction turns an emergent suggestion label into a real,
 * agent-attached topic, converts every suggestion row carrying that label into
 * a committed topic row, and rolls each into analytics (one event per
 * conversation).
 */
function pstConversation(Agent $agent, string $label): Conversation
{
    $conversation = Conversation::factory()->create(['agent_id' => $agent->id]);

    ConversationTopic::create([
        'chat_conversation_id' => $conversation->id,
        'suggested_label' => $label,
    ]);

    return $conversation;
}

it('promotes a suggested label and backfills its conversations', function (): void {
    Event::fake([ConversationTopicAssigned::class]);

    $agent = Agent::factory()->create();
    $conversations = [
        pstConversation($agent, 'Refund Requests'),
        pstConversation($agent, 'Refund Requests'),
    ];

    $topic = app(PromoteSuggestedTopicAction::class)->execute($agent, 'Refund Requests');

    expect($topic->topic)->toBe('Refund Requests')
        ->and($agent->topics()->where('topics.id', $topic->id)->exists())->toBeTrue();

    foreach ($conversations as $conversation) {
        expect(ConversationTopic::query()
            ->where('chat_conversation_id', $conversation->id)
            ->where('topic_id', $topic->id)
            ->exists())->toBeTrue()
            ->and(ConversationTopic::query()
                ->where('chat_conversation_id', $conversation->id)
                ->whereNotNull('suggested_label')
                ->exists())->toBeFalse();
    }

    Event::assertDispatchedTimes(ConversationTopicAssigned::class, 2);
});

it('throws when the agent already has a topic with that name', function (): void {
    $agent = Agent::factory()->create();
    pstConversation($agent, 'Billing');

    // First promotion creates the "Billing" topic.
    app(PromoteSuggestedTopicAction::class)->execute($agent, 'Billing');

    expect(fn (): Topic => app(PromoteSuggestedTopicAction::class)->execute($agent, 'Billing'))
        ->toThrow(DomainValidationException::class);
});

it('promotes the label on old conversations too, not only on recent ones', function (): void {
    Event::fake([ConversationTopicAssigned::class]);

    $agent = Agent::factory()->create();
    $old = pstConversation($agent, 'Shipping Delays');
    $old->forceFill(['created_at' => now()->subDays(200)])->saveQuietly();

    $topic = app(PromoteSuggestedTopicAction::class)->execute($agent, 'Shipping Delays');

    expect(ConversationTopic::query()
        ->where('chat_conversation_id', $old->id)
        ->where('topic_id', $topic->id)
        ->exists())->toBeTrue();
});

it('leaves the same label on other agents\' conversations untouched', function (): void {
    Event::fake([ConversationTopicAssigned::class]);

    $agent = Agent::factory()->create();
    $ours = pstConversation($agent, 'Refund Requests');
    $teammateAgent = Agent::factory()->create(['workspace_id' => $agent->workspace_id]);
    $sameWorkspace = pstConversation($teammateAgent, 'Refund Requests');
    $otherWorkspace = pstConversation(Agent::factory()->create(), 'Refund Requests');

    $topic = app(PromoteSuggestedTopicAction::class)->execute($agent, 'Refund Requests');

    foreach ([$sameWorkspace, $otherWorkspace] as $conversation) {
        expect(ConversationTopic::query()
            ->where('chat_conversation_id', $conversation->id)
            ->where('suggested_label', 'Refund Requests')
            ->exists())->toBeTrue()
            ->and(ConversationTopic::query()
                ->where('chat_conversation_id', $conversation->id)
                ->whereNotNull('topic_id')
                ->exists())->toBeFalse();
    }

    expect(ConversationTopic::query()->where('chat_conversation_id', $ours->id)->where('topic_id', $topic->id)->exists())->toBeTrue();
    Event::assertDispatchedTimes(ConversationTopicAssigned::class, 1);
    Event::assertDispatched(ConversationTopicAssigned::class, fn (ConversationTopicAssigned $event): bool => $event->agentId === $agent->id);
});

it('fires no event for a conversation that already has the topic', function (): void {
    Event::fake([ConversationTopicAssigned::class]);

    $agent = Agent::factory()->create();
    $topic = Topic::create(['workspace_id' => $agent->workspace_id, 'topic' => 'Refund Requests', 'description' => '']);
    $agent->topics()->attach($topic->id);

    $tagged = pstConversation($agent, 'Refund Requests');
    ConversationTopic::create(['chat_conversation_id' => $tagged->id, 'topic_id' => $topic->id]);
    $untagged = pstConversation($agent, 'Refund Requests');

    // Hand back the existing topic, as when the conversation got it before
    // the promotion ran (another promotion of the same label, for one).
    app()->instance(CreateAgentTopicAction::class, new class($topic) extends CreateAgentTopicAction
    {
        public function __construct(private readonly Topic $existing) {}

        public function execute(Agent $agent, string $topic, string $description): Topic
        {
            return $this->existing;
        }
    });

    app(PromoteSuggestedTopicAction::class)->execute($agent, 'Refund Requests');

    // One event, for the untagged conversation only.
    expect(ConversationTopic::query()->where('chat_conversation_id', $tagged->id)->pluck('topic_id')->all())->toBe([$topic->id])
        ->and(ConversationTopic::query()->where('chat_conversation_id', $untagged->id)->pluck('topic_id')->all())->toBe([$topic->id]);
    Event::assertDispatchedTimes(ConversationTopicAssigned::class, 1);
});

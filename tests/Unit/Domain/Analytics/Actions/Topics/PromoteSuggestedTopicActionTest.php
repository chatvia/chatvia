<?php

use Domain\Agent\Models\Agent;
use Domain\Analytics\Actions\Topics\PromoteSuggestedTopicAction;
use Domain\Analytics\Events\ConversationTopicAssigned;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Topic\Actions\CreateAgentTopicAction;
use Domain\Conversation\Topic\Data\SaveTopicData;
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
    ConversationTopic::factory()->forConversation($conversation)->suggested($label)->create();

    return $conversation;
}

/**
 * The topic ids and the suggestion labels on the conversation's rows.
 *
 * @return array{topics: list<int>, suggestions: list<string>}
 */
function pstRows(Conversation $conversation): array
{
    $rows = ConversationTopic::query()->where('chat_conversation_id', $conversation->id)->get();

    return [
        'topics' => $rows->pluck('topic_id')->filter()->values()->all(),
        'suggestions' => $rows->pluck('suggested_label')->filter()->values()->all(),
    ];
}

it('promotes a suggested label and backfills its conversations', function (): void {
    Event::fake([ConversationTopicAssigned::class]);

    $agent = Agent::factory()->create();
    $first = pstConversation($agent, 'Refund Requests');
    $second = pstConversation($agent, 'Refund Requests');

    $topic = app(PromoteSuggestedTopicAction::class)->execute($agent, 'Refund Requests');

    expect($topic->topic)->toBe('Refund Requests')
        ->and($agent->topics()->where('topics.id', $topic->id)->exists())->toBeTrue()
        ->and(pstRows($first))->toBe(['topics' => [$topic->id], 'suggestions' => []])
        ->and(pstRows($second))->toBe(['topics' => [$topic->id], 'suggestions' => []]);

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

    expect(pstRows($old)['topics'])->toBe([$topic->id]);
});

it('leaves the same label on other agents\' conversations untouched', function (): void {
    Event::fake([ConversationTopicAssigned::class]);

    $agent = Agent::factory()->create();
    $ours = pstConversation($agent, 'Refund Requests');
    $teammateAgent = Agent::factory()->create(['workspace_id' => $agent->workspace_id]);
    $sameWorkspace = pstConversation($teammateAgent, 'Refund Requests');
    $otherWorkspace = pstConversation(Agent::factory()->create(), 'Refund Requests');

    $topic = app(PromoteSuggestedTopicAction::class)->execute($agent, 'Refund Requests');

    expect(pstRows($sameWorkspace))->toBe(['topics' => [], 'suggestions' => ['Refund Requests']])
        ->and(pstRows($otherWorkspace))->toBe(['topics' => [], 'suggestions' => ['Refund Requests']])
        ->and(pstRows($ours)['topics'])->toBe([$topic->id]);
    Event::assertDispatchedTimes(ConversationTopicAssigned::class, 1);
    Event::assertDispatched(ConversationTopicAssigned::class, fn (ConversationTopicAssigned $event): bool => $event->agentId === $agent->id);
});

it('fires no event for a conversation that already has the topic', function (): void {
    Event::fake([ConversationTopicAssigned::class]);

    $agent = Agent::factory()->create();
    $topic = Topic::factory()->attachedTo($agent)->create(['topic' => 'Refund Requests']);

    $tagged = pstConversation($agent, 'Refund Requests');
    ConversationTopic::factory()->forConversation($tagged)->forTopic($topic)->create();
    $untagged = pstConversation($agent, 'Refund Requests');

    // Hand back the existing topic, as when the conversation got it before
    // the promotion ran (another promotion of the same label, for one).
    app()->instance(CreateAgentTopicAction::class, new class($topic) extends CreateAgentTopicAction
    {
        public function __construct(private readonly Topic $existing) {}

        public function execute(Agent $agent, SaveTopicData $data): Topic
        {
            return $this->existing;
        }
    });

    app(PromoteSuggestedTopicAction::class)->execute($agent, 'Refund Requests');

    // One event, for the untagged conversation only.
    expect(pstRows($tagged))->toBe(['topics' => [$topic->id], 'suggestions' => []])
        ->and(pstRows($untagged))->toBe(['topics' => [$topic->id], 'suggestions' => []]);
    Event::assertDispatchedTimes(ConversationTopicAssigned::class, 1);
});

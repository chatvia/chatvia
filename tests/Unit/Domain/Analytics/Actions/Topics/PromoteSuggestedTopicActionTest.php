<?php

use Domain\Agent\Models\Agent;
use Domain\Analytics\Actions\Topics\PromoteSuggestedTopicAction;
use Domain\Analytics\Events\ConversationTopicAssigned;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Topic\Models\ConversationTopic;
use Illuminate\Support\Facades\Event;

/**
 * PromoteSuggestedTopicAction turns an emergent suggestion label into a real,
 * agent-attached topic, converts every suggestion row carrying that label into
 * a committed topic row, old ones included, and rolls each into analytics
 * (one event per conversation). Other agents' identical labels stay.
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

it('promotes a suggested label and backfills only this agent\'s conversations, old ones too', function (): void {
    Event::fake([ConversationTopicAssigned::class]);
    $agent = Agent::factory()->create();
    $recent = pstConversation($agent, 'Refund Requests');
    $old = pstConversation($agent, 'Refund Requests');
    $old->forceFill(['created_at' => now()->subDays(200)])->saveQuietly();
    $sameWorkspace = pstConversation(Agent::factory()->create(['workspace_id' => $agent->workspace_id]), 'Refund Requests');
    $otherWorkspace = pstConversation(Agent::factory()->create(), 'Refund Requests');

    $topic = app(PromoteSuggestedTopicAction::class)->execute($agent, 'Refund Requests');

    expect($topic->topic)->toBe('Refund Requests')
        ->and($agent->topics()->where('topics.id', $topic->id)->exists())->toBeTrue()
        ->and(pstRows($recent))->toBe(['topics' => [$topic->id], 'suggestions' => []])
        ->and(pstRows($old))->toBe(['topics' => [$topic->id], 'suggestions' => []])
        ->and(pstRows($sameWorkspace))->toBe(['topics' => [], 'suggestions' => ['Refund Requests']])
        ->and(pstRows($otherWorkspace))->toBe(['topics' => [], 'suggestions' => ['Refund Requests']]);
    Event::assertDispatchedTimes(ConversationTopicAssigned::class, 2);
    Event::assertNotDispatched(ConversationTopicAssigned::class, fn (ConversationTopicAssigned $event): bool => $event->agentId !== $agent->id);
});

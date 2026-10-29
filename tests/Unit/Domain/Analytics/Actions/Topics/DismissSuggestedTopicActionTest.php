<?php

use Domain\Agent\Models\Agent;
use Domain\Analytics\Actions\Topics\DismissSuggestedTopicAction;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Topic\Models\ConversationTopic;

/**
 * Dismissing a suggestion drops its rows for this agent only — another
 * agent's identical label stays, and the agent's other suggestions too.
 */
function dstSuggestion(Agent $agent, string $label): ConversationTopic
{
    return ConversationTopic::factory()
        ->forConversation(Conversation::factory()->create(['agent_id' => $agent->id]))
        ->suggested($label)
        ->create();
}

it('drops every suggestion row of the label for the agent only', function (): void {
    $agent = Agent::factory()->create();
    dstSuggestion($agent, 'Refund Requests');
    dstSuggestion($agent, 'Refund Requests');
    $kept = dstSuggestion($agent, 'Shipping Delays');
    $foreign = dstSuggestion(Agent::factory()->create(), 'Refund Requests');

    app(DismissSuggestedTopicAction::class)->execute($agent, 'Refund Requests');

    expect(ConversationTopic::query()->where('suggested_label', 'Refund Requests')->pluck('id')->all())->toBe([$foreign->id])
        ->and(ConversationTopic::query()->whereKey($kept->id)->exists())->toBeTrue();
});

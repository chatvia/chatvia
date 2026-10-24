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

it('drops every suggestion row of the label for the agent', function (): void {
    $agent = Agent::factory()->create();
    dstSuggestion($agent, 'Refund Requests');
    dstSuggestion($agent, 'Refund Requests');
    $kept = dstSuggestion($agent, 'Shipping Delays');

    app(DismissSuggestedTopicAction::class)->execute($agent, 'Refund Requests');

    expect(ConversationTopic::query()->where('suggested_label', 'Refund Requests')->exists())->toBeFalse()
        ->and(ConversationTopic::query()->whereKey($kept->id)->exists())->toBeTrue();
});

it('leaves another agent\'s identical label untouched', function (): void {
    $agent = Agent::factory()->create();
    $other = Agent::factory()->create();
    dstSuggestion($agent, 'Refund Requests');
    $foreign = dstSuggestion($other, 'Refund Requests');

    app(DismissSuggestedTopicAction::class)->execute($agent, 'Refund Requests');

    expect(ConversationTopic::query()->whereKey($foreign->id)->exists())->toBeTrue();
});

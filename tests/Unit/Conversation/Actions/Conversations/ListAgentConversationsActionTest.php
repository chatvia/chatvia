<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Actions\Conversations\ListAgentConversationsAction;
use Domain\Conversation\Data\Conversations\ConversationSummaryData;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Session;

/**
 * The API conversation list shows only the agent's visible conversations,
 * newest activity first, and hides sentiment/tone unless the workspace's plan
 * includes them.
 */
function apiListedConversation(Agent $agent, array $attributes = []): Conversation
{
    $session = Session::factory()->create(['agent_id' => $agent->id]);

    return Conversation::factory()->create(array_merge([
        'chat_session_id' => $session->id,
        'agent_id' => $agent->id,
        'is_visible' => true,
    ], $attributes));
}

it('lists only the agent\'s visible conversations, latest activity first', function (): void {
    $agent = Agent::factory()->create();
    $older = apiListedConversation($agent, ['updated_at' => now()->subDay()]);
    $newer = apiListedConversation($agent, ['updated_at' => now()]);
    apiListedConversation($agent, ['is_visible' => false]);
    apiListedConversation(Agent::factory()->create());

    $page = app(ListAgentConversationsAction::class)->execute($agent, perPage: 25);

    expect($page->total())->toBe(2)
        ->and(collect($page->items())->map(fn (ConversationSummaryData $row): string => $row->id)->all())
        ->toBe([$newer->external_id, $older->external_id]);
});

it('pages the list with the requested page size', function (): void {
    $agent = Agent::factory()->create();
    foreach (range(1, 3) as $i) {
        apiListedConversation($agent);
    }

    $page = app(ListAgentConversationsAction::class)->execute($agent, perPage: 2);

    expect($page->total())->toBe(3)
        ->and($page->items())->toHaveCount(2)
        ->and($page->perPage())->toBe(2);
});

it('shows sentiment and tone', function (): void {
    $agent = Agent::factory()->create();
    apiListedConversation($agent, ['sentiment' => 'negative', 'tone' => 'frustrated']);

    $row = app(ListAgentConversationsAction::class)->execute($agent, perPage: 25)->items()[0];

    expect($row->sentiment)->toBe('negative')
        ->and($row->tone)->toBe('frustrated');
});

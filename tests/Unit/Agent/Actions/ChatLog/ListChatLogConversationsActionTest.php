<?php

use Domain\Agent\Actions\ChatLog\ListChatLogConversationsAction;
use Domain\Agent\Data\ChatLog\ChatLogFiltersData;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Session;
use Domain\Form\Models\FormDefinition;
use Domain\Form\Models\FormSubmission;

/**
 * The chat log marks which listed conversations captured a lead. It only
 * needs the ids of conversations on the page being shown, not every lead the
 * agent ever captured.
 */
it('returns lead ids only for conversations on the current page', function (): void {
    $agent = Agent::factory()->create();
    $session = Session::factory()->create(['agent_id' => $agent->id]);
    $definition = FormDefinition::factory()->create(['agent_id' => $agent->id]);

    $conversations = Conversation::factory()
        ->count(12)
        ->create(['chat_session_id' => $session->id, 'agent_id' => $agent->id, 'is_visible' => true]);

    foreach ($conversations as $conversation) {
        FormSubmission::factory()->forDefinition($definition)->submitted()->create([
            'chat_conversation_id' => $conversation->id,
        ]);
    }

    $page = app(ListChatLogConversationsAction::class)->execute($agent, new ChatLogFiltersData);

    $externalIds = $page->conversations->map(fn ($row): string => $row->conversation_id);
    $pageIds = Conversation::query()->whereIn('external_id', $externalIds)->pluck('id')->sort()->values()->all();
    $leadIds = $page->lead_conversation_ids->map(fn ($id): int => (int) $id)->sort()->values()->all();

    expect($pageIds)->toHaveCount(10)
        ->and($leadIds)->toBe($pageIds);
});

it('loads the next page from the cursor and has none after the last', function (): void {
    $agent = Agent::factory()->create();
    $session = Session::factory()->create(['agent_id' => $agent->id]);
    $conversations = Conversation::factory()
        ->count(12)
        ->create(['chat_session_id' => $session->id, 'agent_id' => $agent->id, 'is_visible' => true]);
    Conversation::factory()->create(['chat_session_id' => $session->id, 'agent_id' => $agent->id, 'is_visible' => false]);

    $list = app(ListChatLogConversationsAction::class);
    $first = $list->execute($agent, new ChatLogFiltersData);
    $second = $list->execute($agent, new ChatLogFiltersData(cursor: $first->next_cursor));

    $seen = $first->conversations->merge($second->conversations)->map(fn ($row): string => $row->conversation_id);

    expect($first->conversations)->toHaveCount(10)
        ->and($first->next_cursor)->toBeString()
        ->and($second->conversations)->toHaveCount(2)
        ->and($second->next_cursor)->toBeNull()
        ->and($seen->sort()->values()->all())->toBe($conversations->pluck('external_id')->sort()->values()->all());
});

it('keeps the filters on the next page', function (): void {
    $agent = Agent::factory()->create();
    $session = Session::factory()->create(['agent_id' => $agent->id]);
    $negative = Conversation::factory()
        ->count(11)
        ->create(['chat_session_id' => $session->id, 'agent_id' => $agent->id, 'is_visible' => true, 'sentiment' => 'negative']);
    Conversation::factory()
        ->count(3)
        ->create(['chat_session_id' => $session->id, 'agent_id' => $agent->id, 'is_visible' => true, 'sentiment' => 'positive']);

    $list = app(ListChatLogConversationsAction::class);
    $first = $list->execute($agent, new ChatLogFiltersData(sentiment: 'negative'));
    $second = $list->execute($agent, new ChatLogFiltersData(cursor: $first->next_cursor, sentiment: 'negative'));

    $seen = $first->conversations->merge($second->conversations)->map(fn ($row): string => $row->conversation_id);

    expect($second->next_cursor)->toBeNull()
        ->and($seen->sort()->values()->all())->toBe($negative->pluck('external_id')->sort()->values()->all());
});

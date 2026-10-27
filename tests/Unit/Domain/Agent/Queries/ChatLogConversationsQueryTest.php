<?php

use Domain\Agent\Data\ChatLog\ChatLogFiltersData;
use Domain\Agent\Data\Resources\ChatLog\Conversations\ChatLogConversationData;
use Domain\Agent\Models\Agent;
use Domain\Agent\Queries\ChatLogConversationsQuery;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Message;
use Domain\Conversation\Models\Session;
use Domain\Form\Models\FormDefinition;
use Domain\Form\Models\FormSubmission;

/**
 * The chat log marks which listed conversations captured a lead. It only
 * needs the ids of conversations on the page being shown, not every lead the
 * agent ever captured. Pages are keyset-paged, and filters (such as the
 * unanswered one) narrow the list.
 */
it('returns lead ids only for conversations on the current page', function (): void {
    config(['chatvia.license.licensed' => true]);
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

    $page = app(ChatLogConversationsQuery::class)->get($agent, new ChatLogFiltersData);

    $externalIds = $page->conversations->map(fn (ChatLogConversationData $row): string => $row->conversation_id);
    $pageIds = Conversation::query()->whereIn('external_id', $externalIds)->pluck('id')->sort()->values()->all();
    $leadIds = $page->lead_conversation_ids->map(fn (int|string $id): int => (int) $id)->sort()->values()->all();

    expect($pageIds)->toHaveCount(10)
        ->and($leadIds)->toBe($pageIds);
});

it('marks no leads without a license key, since leads need one', function (): void {
    config(['chatvia.license.licensed' => false]);
    $agent = Agent::factory()->create();
    $session = Session::factory()->create(['agent_id' => $agent->id]);
    $conversation = Conversation::factory()->create(['chat_session_id' => $session->id, 'agent_id' => $agent->id, 'is_visible' => true]);
    FormSubmission::factory()->forDefinition(FormDefinition::factory()->create(['agent_id' => $agent->id]))->submitted()->create([
        'chat_conversation_id' => $conversation->id,
    ]);

    $page = app(ChatLogConversationsQuery::class)->get($agent, new ChatLogFiltersData);

    expect($page->conversations)->toHaveCount(1)
        ->and($page->lead_conversation_ids)->toBeEmpty();
});

it('loads the next page from the cursor and has none after the last', function (): void {
    $agent = Agent::factory()->create();
    $session = Session::factory()->create(['agent_id' => $agent->id]);
    $conversations = Conversation::factory()
        ->count(12)
        ->create(['chat_session_id' => $session->id, 'agent_id' => $agent->id, 'is_visible' => true]);
    Conversation::factory()->create(['chat_session_id' => $session->id, 'agent_id' => $agent->id, 'is_visible' => false]);

    $list = app(ChatLogConversationsQuery::class);
    $first = $list->get($agent, new ChatLogFiltersData);
    $second = $list->get($agent, new ChatLogFiltersData(cursor: $first->next_cursor));

    $seen = $first->conversations->merge($second->conversations)->map(fn (ChatLogConversationData $row): string => $row->conversation_id);

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

    $list = app(ChatLogConversationsQuery::class);
    $first = $list->get($agent, new ChatLogFiltersData(sentiment: 'negative'));
    $second = $list->get($agent, new ChatLogFiltersData(cursor: $first->next_cursor, sentiment: 'negative'));

    $seen = $first->conversations->merge($second->conversations)->map(fn (ChatLogConversationData $row): string => $row->conversation_id);

    expect($second->next_cursor)->toBeNull()
        ->and($seen->sort()->values()->all())->toBe($negative->pluck('external_id')->sort()->values()->all());
});

it('lists only the conversations with an unanswered reply when asked', function (): void {
    $agent = Agent::factory()->create();
    $session = Session::factory()->create(['agent_id' => $agent->id]);
    [$gap, $fine] = Conversation::factory()->count(2)->create(['chat_session_id' => $session->id, 'agent_id' => $agent->id, 'is_visible' => true]);
    Message::factory()->fromAssistant()->create(['chat_conversation_id' => $gap->id, 'has_llm_found_answer' => false]);
    Message::factory()->fromAssistant()->create(['chat_conversation_id' => $fine->id, 'has_llm_found_answer' => true]);

    $page = app(ChatLogConversationsQuery::class)->get($agent, new ChatLogFiltersData(unanswered: 'not_found'));

    expect($page->conversations->map(fn (ChatLogConversationData $row): string => $row->conversation_id)->all())->toBe([$gap->external_id]);
});

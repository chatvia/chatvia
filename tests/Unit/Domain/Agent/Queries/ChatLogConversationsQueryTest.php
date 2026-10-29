<?php

use Domain\Agent\Data\ChatLog\ChatLogFiltersData;
use Domain\Agent\Data\Resources\ChatLog\Conversations\ChatLogConversationData;
use Domain\Agent\Models\Agent;
use Domain\Agent\Queries\ChatLogConversationsQuery;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Session;
use Domain\Form\Models\FormDefinition;
use Domain\Form\Models\FormSubmission;

/**
 * The chat log marks which listed conversations captured a lead: only those
 * on the page shown, and none without a license key. Pages are keyset-paged
 * and keep their filters, and hidden conversations are never listed.
 */
it('marks the leads of the conversations on the page, and none without a license key', function (): void {
    config(['chatvia.license.licensed' => true]);
    $agent = Agent::factory()->create();
    $session = Session::factory()->create(['agent_id' => $agent->id]);
    $definition = FormDefinition::factory()->create(['agent_id' => $agent->id]);
    $conversations = Conversation::factory()
        ->count(12)
        ->create(['chat_session_id' => $session->id, 'agent_id' => $agent->id, 'is_visible' => true]);
    foreach ($conversations as $conversation) {
        FormSubmission::factory()->forDefinition($definition)->submitted()->create(['chat_conversation_id' => $conversation->id]);
    }

    $page = app(ChatLogConversationsQuery::class)->get($agent, new ChatLogFiltersData);
    config(['chatvia.license.licensed' => false]);
    $unlicensed = app(ChatLogConversationsQuery::class)->get($agent, new ChatLogFiltersData);

    $externalIds = $page->conversations->map(fn (ChatLogConversationData $row): string => $row->conversation_id);
    $pageIds = Conversation::query()->whereIn('external_id', $externalIds)->pluck('id')->sort()->values()->all();
    $leadIds = $page->lead_conversation_ids->map(fn (int|string $id): int => (int) $id)->sort()->values()->all();

    expect($pageIds)->toHaveCount(10)
        ->and($leadIds)->toBe($pageIds)
        ->and($unlicensed->conversations)->toHaveCount(10)
        ->and($unlicensed->lead_conversation_ids)->toBeEmpty();
});

it('pages through the filtered visible conversations by cursor', function (): void {
    $agent = Agent::factory()->create();
    $session = Session::factory()->create(['agent_id' => $agent->id]);
    $negative = Conversation::factory()
        ->count(11)
        ->create(['chat_session_id' => $session->id, 'agent_id' => $agent->id, 'is_visible' => true, 'sentiment' => 'negative']);
    Conversation::factory()
        ->count(3)
        ->create(['chat_session_id' => $session->id, 'agent_id' => $agent->id, 'is_visible' => true, 'sentiment' => 'positive']);
    Conversation::factory()->create(['chat_session_id' => $session->id, 'agent_id' => $agent->id, 'is_visible' => false, 'sentiment' => 'negative']);

    $list = app(ChatLogConversationsQuery::class);
    $first = $list->get($agent, new ChatLogFiltersData(sentiment: 'negative'));
    $second = $list->get($agent, new ChatLogFiltersData(cursor: $first->next_cursor, sentiment: 'negative'));

    $seen = $first->conversations->merge($second->conversations)->map(fn (ChatLogConversationData $row): string => $row->conversation_id);

    expect($first->conversations)->toHaveCount(10)
        ->and($second->conversations)->toHaveCount(1)
        ->and($second->next_cursor)->toBeNull()
        ->and($seen->sort()->values()->all())->toBe($negative->pluck('external_id')->sort()->values()->all());
});

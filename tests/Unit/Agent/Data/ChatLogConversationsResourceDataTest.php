<?php

use Domain\Agent\Data\Resources\ChatLog\Conversations\ChatLogConversationsResourceData;
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

    $page = ChatLogConversationsResourceData::fromAgent($agent, page: 1);

    $externalIds = collect($page->conversations->items())->map(fn ($row): string => $row->conversation_id);
    $pageIds = Conversation::query()->whereIn('external_id', $externalIds)->pluck('id')->sort()->values()->all();
    $leadIds = $page->lead_conversation_ids->map(fn ($id): int => (int) $id)->sort()->values()->all();

    expect($pageIds)->toHaveCount(10)
        ->and($leadIds)->toBe($pageIds);
});

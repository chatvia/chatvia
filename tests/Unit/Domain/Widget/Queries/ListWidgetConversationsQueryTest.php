<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Session;
use Domain\Widget\Queries\ListWidgetConversationsQuery;
use Illuminate\Support\Str;

/**
 * The widget's conversation list: the threads of the visitor's session, oldest
 * first, without the ones the visitor cleared. The chat cache starts empty in
 * every test, so each read rebuilds the list from the database.
 */
function lwcConversation(Session $session, string $title, array $attributes = []): Conversation
{
    return Conversation::factory()->withTitle($title)->create([
        'chat_session_id' => $session->id,
        'source' => ConversationSource::Widget,
        'is_visible' => true,
        ...$attributes,
    ]);
}

it('lists the session\'s conversations oldest first', function (): void {
    $agent = Agent::factory()->create();
    $session = Session::factory()->create(['agent_id' => $agent->id]);
    $first = lwcConversation($session, 'Opening hours');
    $second = lwcConversation($session, 'Refund', ['is_visible' => false]);
    lwcConversation(Session::factory()->create(['agent_id' => $agent->id]), 'Another visitor');

    $list = app(ListWidgetConversationsQuery::class)->get($agent, $session->external_id);

    expect(collect($list)->toArray())->toBe([
        ['id' => $first->external_id, 'title' => 'Opening hours', 'timestamp' => $first->created_at->timestamp, 'is_visible' => true, 'messages' => []],
        ['id' => $second->external_id, 'title' => 'Refund', 'timestamp' => $second->created_at->timestamp, 'is_visible' => false, 'messages' => []],
    ]);
});

it('leaves out a conversation the visitor cleared', function (): void {
    $agent = Agent::factory()->create();
    $session = Session::factory()->create(['agent_id' => $agent->id]);
    $kept = lwcConversation($session, 'Kept');
    lwcConversation($session, 'Cleared', ['cleared_at' => now()]);

    $ids = array_column(app(ListWidgetConversationsQuery::class)->get($agent, $session->external_id), 'id');

    expect($ids)->toBe([$kept->external_id]);
});

it('lists nothing for a session it has never seen', function (): void {
    expect(app(ListWidgetConversationsQuery::class)->get(Agent::factory()->create(), (string) Str::uuid7()))->toBe([]);
});

it('lists nothing for a session of another agent', function (): void {
    $owner = Agent::factory()->create();
    $session = Session::factory()->create(['agent_id' => $owner->id]);
    lwcConversation($session, 'Private thread');

    expect(app(ListWidgetConversationsQuery::class)->get(Agent::factory()->create(), $session->external_id))->toBe([]);
});

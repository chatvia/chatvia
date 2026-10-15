<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Session;
use Domain\Widget\WidgetHeaders;

/**
 * A session id belongs to one agent. Replayed through another agent's
 * widget, it must show none of that session's conversations and must not
 * start a conversation in it.
 */
it('lists nothing and starts nothing for a session of another agent', function (): void {
    config(['chatvia.widget.always_allowed_origins' => []]);

    $agent = Agent::factory()->create(['is_public' => true]);
    $agent->setSetting('sites', ['customer.example'], 'widget');

    $other = Agent::factory()->create();
    $session = Session::factory()->create(['agent_id' => $other->id]);
    Conversation::factory()->withTitle('Private thread')->create(['chat_session_id' => $session->id, 'is_visible' => true]);

    $this->withHeaders([
        WidgetHeaders::AGENT_ID => $agent->external_id,
        WidgetHeaders::SESSION_ID => $session->external_id,
        'Origin' => 'https://customer.example',
    ])
        ->getJson('/widget/conversations')
        ->assertOk()
        ->assertExactJson(['conversations' => []]);

    expect(Conversation::query()->where('chat_session_id', $session->id)->count())->toBe(1)
        ->and(Session::query()->where('external_id', $session->external_id)->count())->toBe(1);
});

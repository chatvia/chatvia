<?php

use Domain\Agent\Models\Agent;
use Tests\Support\Widget\EmbeddedWidget;

/**
 * POST /widget/handoff/request asks for a human in the visitor's
 * conversation, so it needs the visitor's session.
 */
it('needs a session to request a human', function (): void {
    $agent = Agent::factory()->create(['is_public' => true]);
    EmbeddedWidget::allowSite($agent);

    $this->withHeaders(EmbeddedWidget::headers($agent))
        ->postJson('/widget/handoff/request', ['conversation_id' => 'abc'])
        ->assertStatus(400)
        ->assertJson(['error' => 'Missing session']);
});

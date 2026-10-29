<?php

use Domain\Agent\Models\Agent;
use Tests\Support\Widget\EmbeddedWidget;

/**
 * GET /widget/agents/verify sits outside the origin gate and answers with its
 * own `verified` flag: true only for a call from one of the agent's sites.
 */
it('verifies a call from the agent’s site and no other', function (): void {
    $agent = Agent::factory()->create(['is_public' => true]);
    EmbeddedWidget::allowSite($agent);

    $this->withHeaders(EmbeddedWidget::headers($agent, ['Origin' => 'https://evil.example']))
        ->getJson('/widget/agents/verify')
        ->assertForbidden()
        ->assertExactJson(['verified' => false]);

    $this->withHeaders(EmbeddedWidget::headers($agent))
        ->getJson('/widget/agents/verify')
        ->assertOk()
        ->assertJson(['verified' => true]);
});

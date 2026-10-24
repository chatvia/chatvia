<?php

use Domain\Agent\Models\Agent;
use Tests\Support\Widget\EmbeddedWidget;

/**
 * POST /widget/auth/exchange trades the customer site's sign-in token for a
 * widget session token; with no token it answers 401.
 */
it('answers an auth exchange without a token with 401', function (): void {
    $agent = Agent::factory()->create(['is_public' => true]);
    EmbeddedWidget::allowSite($agent);

    $this->withHeaders(EmbeddedWidget::headers($agent))
        ->postJson('/widget/auth/exchange')
        ->assertUnauthorized()
        ->assertJson(['error' => 'Invalid or expired token']);
});

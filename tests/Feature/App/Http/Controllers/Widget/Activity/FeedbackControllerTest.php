<?php

use Domain\Agent\Models\Agent;
use Tests\Support\Widget\EmbeddedWidget;

/**
 * POST /widget/feedback rates one of the agent's replies, so it must name
 * the message.
 */
it('rejects feedback that names no message', function (): void {
    $agent = Agent::factory()->create(['is_public' => true]);
    EmbeddedWidget::allowSite($agent);

    $this->withHeaders(EmbeddedWidget::chatHeaders($agent))
        ->postJson('/widget/feedback', ['feedback' => true])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('message_id');
});

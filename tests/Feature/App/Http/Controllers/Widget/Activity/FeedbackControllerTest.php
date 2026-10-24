<?php

use Domain\Agent\Models\Agent;
use Illuminate\Support\Str;
use Tests\Support\Widget\EmbeddedWidget;

/**
 * POST /widget/feedback rates one of the agent's replies, so it must name
 * the message; a note on a thumbs-down has a length limit.
 */
it('rejects feedback that names no message', function (): void {
    $agent = Agent::factory()->create(['is_public' => true]);
    EmbeddedWidget::allowSite($agent);

    $this->withHeaders(EmbeddedWidget::chatHeaders($agent))
        ->postJson('/widget/feedback', ['feedback' => true])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('message_id');
});

it('rejects a note longer than the limit', function (): void {
    $agent = Agent::factory()->create(['is_public' => true]);
    EmbeddedWidget::allowSite($agent);

    $this->withHeaders([...EmbeddedWidget::chatHeaders($agent), 'X-MESSAGE-ID' => 'm-1'])
        ->postJson('/widget/feedback', ['feedback' => false, 'comment' => Str::repeat('a', 1001)])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('comment');
});

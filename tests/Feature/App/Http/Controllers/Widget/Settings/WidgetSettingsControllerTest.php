<?php

use Domain\Agent\Models\Agent;
use Tests\Support\Widget\EmbeddedWidget;

/**
 * The widget's settings tell it whether live chat is on: only with a license
 * key and the agent's switch.
 */
it('tells the widget live chat is on only with a license key', function (bool $licensed, bool $sent): void {
    $licensed ? withLicense() : withoutLicense();
    $agent = Agent::factory()->create(['is_public' => true]);
    $agent->setSetting('handoff_enabled', true, 'widget');
    EmbeddedWidget::allowSite($agent);

    $this->withHeaders(EmbeddedWidget::headers($agent))
        ->getJson('/widget/agents/settings')
        ->assertOk()
        ->assertJsonPath('handoff_enabled', $sent);
})->with([
    'with a key' => [true, true],
    'without a key' => [false, false],
]);

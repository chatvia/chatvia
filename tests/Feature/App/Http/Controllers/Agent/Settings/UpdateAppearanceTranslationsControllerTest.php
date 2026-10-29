<?php

use Domain\Agent\Models\Agent;

/**
 * The owner's own per-language edits of the appearance texts: kept apart from
 * the AI translations, empty values dropped.
 */
it('stores manual per-language overrides and drops empty values', function (): void {
    $agent = Agent::factory()->create();

    $this->actingAs($agent->workspace->owner)
        ->putJson(route('agents.appearance.translations.update', $agent->external_id), [
            'overrides' => [
                'welcome_message' => ['de' => 'Servus!', 'fr' => ''],
            ],
        ])
        ->assertOk();

    expect($agent->fresh()->settings('appearance')->get('text_overrides'))
        ->toBe(['welcome_message' => ['de' => 'Servus!']]);
});

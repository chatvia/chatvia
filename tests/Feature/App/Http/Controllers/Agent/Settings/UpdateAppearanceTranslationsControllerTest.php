<?php

use Domain\Agent\Models\Agent;
use Illuminate\Support\Str;

/**
 * The owner's own per-language edits of the appearance texts: kept apart from
 * the AI translations, empty values dropped, an oversized one refused.
 */
beforeEach(function (): void {
    $this->agent = Agent::factory()->create();
    $this->owner = $this->agent->workspace->owner;
});

it('stores manual per-language overrides and drops empty values', function (): void {
    $this->actingAs($this->owner)
        ->putJson(route('agents.appearance.translations.update', $this->agent->external_id), [
            'overrides' => [
                'welcome_message' => ['de' => 'Servus!', 'fr' => ''],
            ],
        ])
        ->assertOk();

    expect($this->agent->fresh()->settings('appearance')->get('text_overrides'))
        ->toBe(['welcome_message' => ['de' => 'Servus!']]);
});

it('rejects an oversized override value', function (): void {
    $this->actingAs($this->owner)
        ->putJson(route('agents.appearance.translations.update', $this->agent->external_id), [
            'overrides' => [
                'welcome_message' => ['de' => Str::repeat('x', 501)],
            ],
        ])
        ->assertJsonValidationErrors('overrides.welcome_message.de');

    expect($this->agent->fresh()->settings('appearance')->get('text_overrides'))->toBeEmpty();
});

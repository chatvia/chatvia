<?php

use Domain\Agent\Models\Agent;

/**
 * The generic `settings/{group}` endpoint only manages groups that have
 * validation rules, and only deletes keys that belong to the group.
 */
beforeEach(function (): void {
    $this->agent = Agent::factory()->create();
    $this->owner = $this->agent->workspace->owner;
});

it('returns 404 for a settings group without validation rules', function (): void {
    // `leads` has defaults but no rules, so there is nothing to validate it with.
    $this->actingAs($this->owner)
        ->postJson(route('agents.settings.update', ['agent' => $this->agent->external_id, 'group' => 'leads']), ['fields' => []])
        ->assertNotFound();

    $this->actingAs($this->owner)
        ->deleteJson(route('agents.settings.delete', ['agent' => $this->agent->external_id, 'group' => 'leads']), ['key' => 'fields'])
        ->assertNotFound();
});

it('returns 404 for an unknown settings group', function (): void {
    $this->actingAs($this->owner)
        ->postJson(route('agents.settings.update', ['agent' => $this->agent->external_id, 'group' => 'nope']), [])
        ->assertNotFound();
});

it('saves a setting of a managed group', function (): void {
    $this->actingAs($this->owner)
        ->postJson(route('agents.settings.update', ['agent' => $this->agent->external_id, 'group' => 'embed']), ['default_theme' => 'dark'])
        ->assertOk();

    expect($this->agent->fresh()->settings('embed')->get('default_theme'))->toBe('dark');
});

it('deletes a key that belongs to the group', function (): void {
    $this->agent->setSetting(key: 'default_theme', value: 'dark', group: 'embed');

    $this->actingAs($this->owner)
        ->deleteJson(route('agents.settings.delete', ['agent' => $this->agent->external_id, 'group' => 'embed']), ['key' => 'default_theme'])
        ->assertOk();

    expect($this->agent->fresh()->modelSettings()->where('group', 'embed')->where('key', 'default_theme')->exists())->toBeFalse();
});

it('refuses to delete a key that is not part of the group', function (string $key): void {
    $this->agent->setSetting(key: 'chat_rate_limit_per_minute', value: 5, group: 'security');

    $this->actingAs($this->owner)
        ->deleteJson(route('agents.settings.delete', ['agent' => $this->agent->external_id, 'group' => 'embed']), ['key' => $key])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('key');

    expect($this->agent->fresh()->settings('security')->get('chat_rate_limit_per_minute'))->toBe(5);
})->with(['chat_rate_limit_per_minute', 'anything']);

it('does not write groups that have their own, stricter endpoint', function (string $group, string $key, mixed $value): void {
    // The rate limit needs agent.identity.manage and the guardrails need the
    // guardrails capability + permission; agent.setup alone must not reach them.
    $this->actingAs($this->owner)
        ->postJson(route('agents.settings.update', ['agent' => $this->agent->external_id, 'group' => $group]), [$key => $value])
        ->assertNotFound();

    expect($this->agent->fresh()->modelSettings()->where('group', $group)->where('key', $key)->exists())->toBeFalse();
})->with([
    'rate limit' => ['security', 'chat_rate_limit_per_minute', 999],
    'guardrails' => ['guardrails', 'enabled', true],
    'model' => ['llm_settings', 'temperature', 1.5],
]);

it('saves the voice-conversation switch and the agent\'s voice', function (): void {
    $this->actingAs($this->owner)
        ->postJson(route('agents.settings.update', ['agent' => $this->agent->external_id, 'group' => 'appearance']), [
            'allow_voice_conversation' => true,
            'voice' => 'cedar',
        ])
        ->assertOk();

    $appearance = $this->agent->fresh()->settings('appearance');
    expect($appearance->get('allow_voice_conversation'))->toBeTrue()
        ->and($appearance->get('voice'))->toBe('cedar');
});

it('refuses a voice that does not exist', function (): void {
    $this->actingAs($this->owner)
        ->postJson(route('agents.settings.update', ['agent' => $this->agent->external_id, 'group' => 'appearance']), ['voice' => 'robot'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('voice');
});

it('saves how the answers show their sources, and refuses an unknown way', function (): void {
    expect($this->agent->showsSources())->toBeFalse();

    $this->actingAs($this->owner)
        ->postJson(route('agents.settings.update', ['agent' => $this->agent->external_id, 'group' => 'appearance']), ['sources_display' => 'citations'])
        ->assertOk();

    expect($this->agent->fresh()->citesSources())->toBeTrue()
        ->and($this->agent->fresh()->showsSources())->toBeTrue();

    $this->actingAs($this->owner)
        ->postJson(route('agents.settings.update', ['agent' => $this->agent->external_id, 'group' => 'appearance']), ['sources_display' => 'hidden'])
        ->assertOk();

    expect($this->agent->fresh()->showsSources())->toBeFalse();

    $this->actingAs($this->owner)
        ->postJson(route('agents.settings.update', ['agent' => $this->agent->external_id, 'group' => 'appearance']), ['sources_display' => 'popup'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('sources_display');
});

it('saves the UI languages the owner picks', function (): void {
    $this->actingAs($this->owner)
        ->post(route('agents.settings.update', ['agent' => $this->agent->external_id, 'group' => 'appearance']), [
            'enabled_languages' => ['de', 'fr'],
        ])
        ->assertSessionHasNoErrors();

    expect($this->agent->fresh()->settings('appearance')->get('enabled_languages'))->toBe(['de', 'fr']);
});

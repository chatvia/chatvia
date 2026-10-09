<?php

/**
 * The generic `settings/{group}` endpoint only manages groups that have
 * validation rules, and only deletes keys that belong to the group.
 */
beforeEach(function (): void {
    $this->agent = proPlanAgent();
    $this->owner = $this->agent->user;
});

it('returns 404 for a settings group without validation rules', function (): void {
    // `leads` has defaults but no rules; validating it used to throw a 500.
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

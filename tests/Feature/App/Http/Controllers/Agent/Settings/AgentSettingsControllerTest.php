<?php

use Domain\Agent\Models\Agent;

/**
 * The generic `settings/{group}` endpoint only manages groups that have
 * validation rules and no stricter endpoint of their own, and only deletes
 * keys that belong to the group.
 */
beforeEach(function (): void {
    $this->agent = Agent::factory()->create();
    $this->owner = $this->agent->workspace->owner;
});

// The rate limit needs agent.identity.manage and the guardrails need the
// guardrails capability and permission; agent.setup alone must not reach them.
it('writes nothing to a group without rules or with its own stricter endpoint', function (string $group, string $key, mixed $value): void {
    $this->actingAs($this->owner)
        ->postJson(route('agents.settings.update', ['agent' => $this->agent->external_id, 'group' => $group]), [$key => $value])
        ->assertNotFound();

    expect($this->agent->fresh()->modelSettings()->where('group', $group)->where('key', $key)->exists())->toBeFalse();
})->with([
    'no rules' => ['leads', 'fields', []],
    'unknown group' => ['nope', 'anything', 'x'],
    'rate limit' => ['security', 'chat_rate_limit_per_minute', 999],
    'guardrails' => ['guardrails', 'enabled', true],
    'model' => ['llm_settings', 'temperature', 1.5],
]);

it('deletes a key of the group and refuses a key of another group', function (): void {
    $this->agent->setSetting(key: 'default_theme', value: 'dark', group: 'embed');
    $this->agent->setSetting(key: 'chat_rate_limit_per_minute', value: 5, group: 'security');
    $url = route('agents.settings.delete', ['agent' => $this->agent->external_id, 'group' => 'embed']);

    $this->actingAs($this->owner)->deleteJson($url, ['key' => 'chat_rate_limit_per_minute'])->assertJsonValidationErrors('key');
    $this->actingAs($this->owner)->deleteJson($url, ['key' => 'default_theme'])->assertOk();

    expect($this->agent->fresh()->settings('security')->get('chat_rate_limit_per_minute'))->toBe(5)
        ->and($this->agent->fresh()->modelSettings()->where('group', 'embed')->where('key', 'default_theme')->exists())->toBeFalse();
});

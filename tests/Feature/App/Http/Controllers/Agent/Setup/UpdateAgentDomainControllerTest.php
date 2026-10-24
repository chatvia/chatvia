<?php

use App\Identity\Workspace\Models\User;

/**
 * An agent's custom domain: stored in lower case, cleared when empty, and
 * refused when it is the app's own host, another agent's domain or not a
 * bare host name.
 */
beforeEach(function (): void {
    config(['app.url' => 'https://app.chatvia.test']);
    $this->owner = User::factory()->create();
    $this->agent = createAgent($this->owner, ['domain' => 'chat.acme-bot.test']);
});

it('stores the custom domain trimmed and in lower case', function (): void {
    $this->actingAs($this->owner)
        ->patchJson(route('agents.domain.update', $this->agent), ['domain' => ' Help.Acme-Bot.TEST '])
        ->assertRedirect();

    expect($this->agent->fresh()->domain)->toBe('help.acme-bot.test');
});

it('clears the custom domain when it is sent empty', function (): void {
    $this->actingAs($this->owner)
        ->patchJson(route('agents.domain.update', $this->agent), ['domain' => ''])
        ->assertRedirect();

    expect($this->agent->fresh()->domain)->toBeNull();
});

it('refuses a domain the agent cannot have', function (string $domain): void {
    createAgent($this->owner, ['domain' => 'taken.acme-bot.test']);

    $this->actingAs($this->owner)
        ->patchJson(route('agents.domain.update', $this->agent), ['domain' => $domain])
        ->assertJsonValidationErrors(['domain']);

    expect($this->agent->fresh()->domain)->toBe('chat.acme-bot.test');
})->with([
    'the app\'s own host' => 'app.chatvia.test',
    'another agent\'s domain' => 'taken.acme-bot.test',
    'a URL, not a host' => 'https://chat.acme-bot.test/path',
]);

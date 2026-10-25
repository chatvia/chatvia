<?php

use App\Identity\Workspace\Models\User;
use Tests\Support\Agent\FakeDnsTxtResolver;

/**
 * POST agents/{agent}/domain/verify turns a pending custom domain on once its
 * `_chatvia.<domain>` TXT record holds the agent's token. Until then the
 * domain does not show the agent's chat.
 */
beforeEach(function (): void {
    config(['app.url' => 'https://app.chatvia.test']);
    $this->owner = User::factory()->create();
    $this->agent = createAgent($this->owner, [
        'pending_domain' => 'help.acme-bot.test',
        'domain_verification_token' => 'chatvia-verify-abc',
    ]);
});

it('does not show the chat on a domain that is still pending', function (): void {
    $this->get('http://help.acme-bot.test/')->assertRedirect(route('login', absolute: false));
});

it('turns the domain on once the TXT record holds the token, and the domain then shows the chat', function (): void {
    FakeDnsTxtResolver::bind(['_chatvia.help.acme-bot.test' => ['chatvia-verify-abc']]);

    $this->actingAs($this->owner)
        ->postJson(route('agents.domain.verify', $this->agent))
        ->assertRedirect();

    expect($this->agent->fresh())->domain->toBe('help.acme-bot.test')->pending_domain->toBeNull();

    auth()->logout();

    $this->get('http://help.acme-bot.test/')
        ->assertRedirect(route('chat.home', ['agentSlug' => $this->agent->slug], absolute: false));
});

it('answers a missing record with a field error and leaves the domain pending', function (): void {
    FakeDnsTxtResolver::bind();

    $this->actingAs($this->owner)
        ->postJson(route('agents.domain.verify', $this->agent))
        ->assertJsonValidationErrors(['domain']);

    expect($this->agent->fresh()->domain)->toBeNull();
});

it('needs agent.setup', function (): void {
    FakeDnsTxtResolver::bind(['_chatvia.help.acme-bot.test' => ['chatvia-verify-abc']]);

    $this->actingAs(teammateWithPermissions($this->owner, allAgentPermissionsBut('agent.setup')))
        ->postJson(route('agents.domain.verify', $this->agent))
        ->assertForbidden();

    expect($this->agent->fresh()->domain)->toBeNull();
});

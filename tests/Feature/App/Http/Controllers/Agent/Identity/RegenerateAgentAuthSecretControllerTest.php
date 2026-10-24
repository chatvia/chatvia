<?php

use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Services\AgentSignInTokenService;

/**
 * Renewing an agent's signing secret stops every sign-in token signed with the
 * old one; tokens issued after it work.
 */
it('stops every sign-in token signed with the old secret once the secret is renewed', function (): void {
    $owner = User::factory()->create();
    $agent = createAgent($owner);
    $chatter = Chatter::factory()->grantedTo($agent)->create();
    $tokens = app(AgentSignInTokenService::class);
    $token = $tokens->issue($chatter, $agent)['token'];

    expect($tokens->verify($token, $agent->fresh())?->is($chatter))->toBeTrue();

    $this->actingAs($owner)
        ->post(route('agents.identity.regenerate', $agent))
        ->assertRedirect();

    $renewed = $agent->fresh();

    expect($tokens->verify($token, $renewed))->toBeNull()
        ->and($tokens->verify($tokens->issue($chatter, $renewed)['token'], $renewed)?->is($chatter))->toBeTrue();
});

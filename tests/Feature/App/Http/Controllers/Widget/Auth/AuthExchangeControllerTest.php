<?php

use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Services\AgentSignInTokens;
use Domain\Agent\Models\Agent;
use Firebase\JWT\JWT;
use Tests\Support\Widget\EmbeddedWidget;

/**
 * POST /widget/auth/exchange trades the customer site's sign-in token for a
 * widget session token; with no token it answers 401. A token signed with
 * the agent's secret opens a chatter's session only, never a staff one.
 */
it('answers an auth exchange without a token with 401', function (): void {
    $agent = Agent::factory()->create(['is_public' => true]);
    EmbeddedWidget::allowSite($agent);

    $this->withHeaders(EmbeddedWidget::headers($agent))
        ->postJson('/widget/auth/exchange')
        ->assertUnauthorized()
        ->assertJson(['error' => 'Invalid or expired token']);
});

it('refuses a staff account in a token signed with the agent secret, but takes a server-issued one', function (): void {
    // A teammate can read the agent's secret, so a token signed with it must
    // not open a widget session as the owner.
    $agent = Agent::factory()->create(['is_public' => false]);
    EmbeddedWidget::allowSite($agent);
    $owner = $agent->workspace->owner;
    $chatter = Chatter::factory()->grantedTo($agent)->create();
    $exp = now()->addMinutes(10)->getTimestamp();
    $sign = fn (string $sub): string => JWT::encode(['sub' => $sub, 'aud' => $agent->external_id, 'exp' => $exp], $agent->ensureAuthSecret(), 'HS256');
    $exchange = fn (string $token) => $this->withHeaders(EmbeddedWidget::headers($agent))
        ->postJson('/widget/auth/exchange', ['token' => $token]);

    $exchange($sign($owner->external_id))->assertUnauthorized();
    $exchange($sign($chatter->external_id))->assertOk()->assertJsonStructure(['auth_token', 'expires_at']);
    $exchange(app(AgentSignInTokens::class)->issue($owner, $agent)['token'])->assertOk();
});

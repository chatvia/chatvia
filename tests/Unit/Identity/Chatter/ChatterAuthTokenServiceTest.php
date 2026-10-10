<?php

use App\Identity\Chatter\Services\ChatterAuthTokenService;
use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Services\WidgetAuthSessionService;
use Domain\Agent\Models\Agent;
use Illuminate\Http\Request;

/**
 * The one-time `?auth_token=` that carries a sign-in into an agent's hosted
 * chat: it works for its own agent, once, and no other cached value can pose
 * as one.
 */
beforeEach(function (): void {
    $this->tokens = app(ChatterAuthTokenService::class);
    $this->user = User::factory()->create();
    $this->agent = Agent::factory()->create(['user_id' => $this->user->id]);
});

it('signs in the user it was minted for, once', function (): void {
    $token = $this->tokens->generateToken($this->agent, $this->user, 5);

    expect($this->tokens->consume($token, $this->agent))->toBe($this->user->id)
        ->and($this->tokens->consume($token, $this->agent))->toBeNull();
});

it('refuses a token minted for another agent, and uses it up', function (): void {
    $other = Agent::factory()->create();
    $token = $this->tokens->generateToken($this->agent, $this->user, 5);

    expect($this->tokens->consume($token, $other))->toBeNull()
        ->and($this->tokens->consume($token, $this->agent))->toBeNull();
});

it('refuses a widget session token', function (): void {
    $issued = app(WidgetAuthSessionService::class)->issue($this->user, $this->agent, Request::create('/'));

    expect($this->tokens->consume('widget:auth:'.$issued['token'], $this->agent))->toBeNull()
        ->and($this->tokens->consume($issued['token'], $this->agent))->toBeNull();
});

it('refuses an empty or unknown token', function (): void {
    expect($this->tokens->consume('', $this->agent))->toBeNull()
        ->and($this->tokens->consume('nope', $this->agent))->toBeNull();
});

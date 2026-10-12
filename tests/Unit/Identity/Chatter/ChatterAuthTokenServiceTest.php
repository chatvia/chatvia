<?php

use App\Identity\Chatter\Models\Chatter;
use App\Identity\Chatter\Services\ChatterAuthTokenService;
use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Services\WidgetAuthSessionService;
use Domain\Agent\Models\Agent;
use Illuminate\Http\Request;

/**
 * The one-time `?auth_token=` that carries a sign-in into an agent's hosted
 * chat: it works for its own agent, once, and no other cached value can pose
 * as one. It remembers whether it was minted for a chatter or a staff
 * account, whose ids come from two tables and can be equal.
 */
beforeEach(function (): void {
    $this->tokens = app(ChatterAuthTokenService::class);
    $this->user = User::factory()->create();
    $this->agent = Agent::factory()->create(['workspace_id' => $this->user->workspace()->id]);
});

it('signs in the staff account it was minted for, once', function (): void {
    $token = $this->tokens->generateToken($this->agent, $this->user, 5);

    $account = $this->tokens->consume($token, $this->agent);

    expect($account)->toBeInstanceOf(User::class)
        ->and($account?->is($this->user))->toBeTrue()
        ->and($this->tokens->consume($token, $this->agent))->toBeNull();
});

it('signs in the chatter it was minted for, once', function (): void {
    $chatter = Chatter::factory()->grantedTo($this->agent)->create();
    $token = $this->tokens->generateToken($this->agent, $chatter, 5);

    $account = $this->tokens->consume($token, $this->agent);

    expect($account)->toBeInstanceOf(Chatter::class)
        ->and($account?->is($chatter))->toBeTrue()
        ->and($this->tokens->consume($token, $this->agent))->toBeNull();
});

it('resolves a chatter token to the chatter, never the staff account with the same id', function (): void {
    // One id taken from both id sequences, so neither table already holds it.
    $id = sharedStaffAndChatterId();
    $staff = User::factory()->create(['id' => $id]);
    $chatter = Chatter::factory()->grantedTo($this->agent)->create(['id' => $id]);

    $chatterAccount = $this->tokens->consume($this->tokens->generateToken($this->agent, $chatter, 5), $this->agent);
    $staffAccount = $this->tokens->consume($this->tokens->generateToken($this->agent, $staff, 5), $this->agent);

    expect($chatterAccount)->toBeInstanceOf(Chatter::class)
        ->and($chatterAccount?->is($chatter))->toBeTrue()
        ->and($staffAccount)->toBeInstanceOf(User::class)
        ->and($staffAccount?->is($staff))->toBeTrue();
});

it('refuses a chatter token once the chatter is no longer in the agent\'s workspace', function (): void {
    $chatter = Chatter::factory()->grantedTo($this->agent)->create();
    $token = $this->tokens->generateToken($this->agent, $chatter, 5);

    // Its grants pin it to the workspace, so they go first.
    $chatter->agents()->detach();
    $chatter->update(['workspace_id' => User::factory()->create()->workspace()->id]);

    expect($this->tokens->consume($token, $this->agent))->toBeNull();
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

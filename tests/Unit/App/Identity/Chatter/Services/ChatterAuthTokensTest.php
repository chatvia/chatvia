<?php

use App\Identity\Chatter\Models\Chatter;
use App\Identity\Chatter\Services\ChatterAuthTokens;
use App\Identity\Workspace\Data\Sessions\ClientOriginData;
use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Services\WidgetAuthSessions;
use Domain\Agent\Models\Agent;

/**
 * The one-time `?auth_token=` that carries a sign-in into an agent's hosted
 * chat: it works for its own agent, once, and no other cached value can pose
 * as one. It remembers whether it was minted for a chatter or a staff
 * account, whose ids come from two tables and can be equal.
 */
beforeEach(function (): void {
    $this->tokens = app(ChatterAuthTokens::class);
    $this->user = User::factory()->create();
    $this->agent = Agent::factory()->create(['workspace_id' => $this->user->workspace()->id]);
});

it('signs in the chatter or staff account it was minted for once, never the other with the same id', function (): void {
    $id = sharedStaffAndChatterId();
    $staff = User::factory()->create(['id' => $id]);
    $chatter = Chatter::factory()->grantedTo($this->agent)->create(['id' => $id]);
    $chatterToken = $this->tokens->generateToken($this->agent, $chatter, 5);
    $staffToken = $this->tokens->generateToken($this->agent, $staff, 5);

    $chatterAccount = $this->tokens->consume($chatterToken, $this->agent);
    $staffAccount = $this->tokens->consume($staffToken, $this->agent);

    expect($chatterAccount)->toBeInstanceOf(Chatter::class)
        ->and($chatterAccount?->is($chatter))->toBeTrue()
        ->and($staffAccount)->toBeInstanceOf(User::class)
        ->and($staffAccount?->is($staff))->toBeTrue()
        ->and($this->tokens->consume($chatterToken, $this->agent))->toBeNull()
        ->and($this->tokens->consume($staffToken, $this->agent))->toBeNull();
});

it('refuses a token minted for another agent, using it up, and one for a chatter who left the workspace', function (): void {
    $other = Agent::factory()->create();
    $staffToken = $this->tokens->generateToken($this->agent, $this->user, 5);
    $chatter = Chatter::factory()->grantedTo($this->agent)->create();
    $chatterToken = $this->tokens->generateToken($this->agent, $chatter, 5);
    $chatter->agents()->detach();
    $chatter->update(['workspace_id' => User::factory()->create()->workspace()->id]);

    expect($this->tokens->consume($staffToken, $other))->toBeNull()
        ->and($this->tokens->consume($staffToken, $this->agent))->toBeNull()
        ->and($this->tokens->consume($chatterToken, $this->agent))->toBeNull();
});

it('refuses a widget session token and an empty or unknown token', function (): void {
    $issued = app(WidgetAuthSessions::class)->issue($this->user, $this->agent, new ClientOriginData('127.0.0.1', 'Symfony'));

    expect($this->tokens->consume('widget:auth:'.$issued['token'], $this->agent))->toBeNull()
        ->and($this->tokens->consume($issued['token'], $this->agent))->toBeNull()
        ->and($this->tokens->consume('', $this->agent))->toBeNull()
        ->and($this->tokens->consume('nope', $this->agent))->toBeNull();
});

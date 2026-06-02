<?php

use App\Workspace\Models\User;
use App\Workspace\Actions\Sessions\LoginUserToAgentAction;
use App\Workspace\Data\Sessions\LoginData;
use Illuminate\Support\Facades\Hash;

beforeEach(function (): void {
    $this->owner = User::factory()->create([
        'email' => 'owner@example.test',
        'password' => Hash::make('correct-password'),
    ]);
    $this->agent = createAgent($this->owner);
});

it('returns the authenticated user and logs them in on valid credentials', function (): void {
    $user = app(LoginUserToAgentAction::class)->execute(
        new LoginData(email: 'owner@example.test', password: 'correct-password', remember: false),
        $this->agent,
    );

    expect($user)->toBeInstanceOf(User::class)
        ->and($user->id)->toBe($this->owner->id)
        ->and(auth()->id())->toBe($this->owner->id);
});

it('stamps last_login_at when the login succeeds', function (): void {
    expect($this->owner->last_login_at)->toBeNull();

    app(LoginUserToAgentAction::class)->execute(
        new LoginData(email: 'owner@example.test', password: 'correct-password', remember: false),
        $this->agent,
    );

    expect($this->owner->fresh()->last_login_at)->not->toBeNull();
});

it('returns null and does not log in when the password is wrong', function (): void {
    $result = app(LoginUserToAgentAction::class)->execute(
        new LoginData(email: 'owner@example.test', password: 'wrong-password', remember: false),
        $this->agent,
    );

    expect($result)->toBeNull()
        ->and(auth()->check())->toBeFalse();
});

it('returns null when no user matches the email', function (): void {
    $result = app(LoginUserToAgentAction::class)->execute(
        new LoginData(email: 'no-such-user@example.test', password: 'correct-password', remember: false),
        $this->agent,
    );

    expect($result)->toBeNull()
        ->and(auth()->check())->toBeFalse();
});

it('returns null when a chatter has no grant for the given agent', function (): void {
    $workspace = User::factory()->workspace()->create();
    $chatter = User::factory()->chatter($workspace)->create([
        'email' => 'chatter@example.test',
        'password' => Hash::make('chatter-pass'),
    ]);
    $otherAgent = createAgent($workspace);

    $result = app(LoginUserToAgentAction::class)->execute(
        new LoginData(email: 'chatter@example.test', password: 'chatter-pass', remember: false),
        $otherAgent,
    );

    expect($result)->toBeNull()
        ->and(auth()->check())->toBeFalse();
});

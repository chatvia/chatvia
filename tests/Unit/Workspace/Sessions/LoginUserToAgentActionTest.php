<?php

use App\Identity\Workspace\Actions\Account\DeactivateAccountAction;
use App\Identity\Workspace\Actions\Sessions\LoginUserToAgentAction;
use App\Identity\Workspace\Data\Sessions\LoginData;
use App\Identity\Workspace\Enums\UserType;
use App\Identity\Workspace\Models\User;
use Illuminate\Support\Facades\Hash;
use Support\Exceptions\DomainValidationException;

beforeEach(function (): void {
    $this->owner = User::factory()->create([
        'email' => 'owner@example.test',
        'password' => Hash::make('correct-password'),
    ]);
    $this->agent = createAgent($this->owner);

    $this->chatter = User::factory()->chatter($this->owner)->create([
        'email' => 'chatter@example.test',
        'password' => Hash::make('chatter-pass'),
    ]);
    $this->chatter->accessibleAgents()->attach($this->agent);
});

function loginToAgentChat(string $email, string $password): ?User
{
    return app(LoginUserToAgentAction::class)->execute(
        new LoginData(email: $email, password: $password, remember: false),
        test()->agent,
    );
}

it('logs in a chatter who has a grant for the agent', function (): void {
    $user = loginToAgentChat('chatter@example.test', 'chatter-pass');

    expect($user)->toBeInstanceOf(User::class)
        ->and($user->id)->toBe($this->chatter->id)
        ->and(auth()->id())->toBe($this->chatter->id);
});

it('stamps last_login_at when the login succeeds', function (): void {
    expect($this->chatter->last_login_at)->toBeNull();

    loginToAgentChat('chatter@example.test', 'chatter-pass');

    expect($this->chatter->fresh()->last_login_at)->not->toBeNull();
});

it('returns null and does not log in when the password is wrong', function (): void {
    expect(loginToAgentChat('chatter@example.test', 'wrong-password'))->toBeNull()
        ->and(auth()->check())->toBeFalse();
});

it('returns null when the chatter is deactivated, even with valid credentials', function (): void {
    app(DeactivateAccountAction::class)->execute($this->chatter, 'blocked by admin');

    expect(loginToAgentChat('chatter@example.test', 'chatter-pass'))->toBeNull()
        ->and(auth()->check())->toBeFalse();
});

it('returns null when no user matches the email', function (): void {
    expect(loginToAgentChat('no-such-user@example.test', 'chatter-pass'))->toBeNull()
        ->and(auth()->check())->toBeFalse();
});

it('still computes a password hash when no user matches the email', function (): void {
    // Same work as a wrong password for a real account, so response timing
    // can't reveal which emails are registered.
    Hash::shouldReceive('make')->once()->andReturn('hashed');

    expect(loginToAgentChat('no-such-user@example.test', 'chatter-pass'))->toBeNull();
});

it('returns null when a chatter has no grant for the given agent', function (): void {
    $workspace = User::factory()->workspace()->create();
    User::factory()->chatter($workspace)->create([
        'email' => 'outsider@example.test',
        'password' => Hash::make('outsider-pass'),
    ]);

    expect(loginToAgentChat('outsider@example.test', 'outsider-pass'))->toBeNull()
        ->and(auth()->check())->toBeFalse();
});

it('refuses a 2FA-enabled owner so the password form cannot skip the second factor', function (): void {
    $this->owner->forceFill([
        'two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'),
        'two_factor_confirmed_at' => now(),
    ])->save();

    expect(fn (): ?\App\Identity\Workspace\Models\User => loginToAgentChat('owner@example.test', 'correct-password'))
        ->toThrow(DomainValidationException::class);

    expect(auth()->check())->toBeFalse();
});

it('refuses every non-chatter account type, even with access to the agent', function (UserType $type): void {
    $user = User::factory()->create([
        'type' => $type,
        'email' => 'staff@example.test',
        'password' => Hash::make('staff-pass'),
    ]);

    expect(fn (): ?\App\Identity\Workspace\Models\User => loginToAgentChat('staff@example.test', 'staff-pass'))
        ->toThrow(DomainValidationException::class);

    expect(auth()->check())->toBeFalse()
        ->and($user->fresh()->last_login_at)->toBeNull();
})->with([
    'workspace owner' => [UserType::Workspace],
    'admin' => [UserType::Admin],
    'employee' => [UserType::Employee],
]);

<?php

use App\Identity\Chatter\Actions\LoginChatterToAgentAction;
use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Actions\Account\DeactivateAccountAction;
use App\Identity\Workspace\Data\Sessions\LoginData;
use App\Identity\Workspace\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * The hosted chat's password check. The chatter is looked up only inside the
 * agent's workspace, so the same email can be a chatter elsewhere, and staff
 * accounts never match. Every failure looks the same to the caller.
 */
beforeEach(function (): void {
    $this->owner = User::factory()->create([
        'email' => 'owner@example.test',
        'password' => Hash::make('correct-password'),
    ]);
    $this->agent = createAgent($this->owner);

    $this->chatter = Chatter::factory()->grantedTo($this->agent)->create([
        'email' => 'chatter@example.test',
        'password' => Hash::make('chatter-pass'),
    ]);
});

function loginChatterToAgentChat(string $email, string $password): ?Chatter
{
    return app(LoginChatterToAgentAction::class)->execute(
        new LoginData(email: $email, password: $password, remember: false),
        test()->agent,
    );
}

it('returns the chatter who has a grant for the agent', function (): void {
    expect(loginChatterToAgentChat('chatter@example.test', 'chatter-pass')?->id)->toBe($this->chatter->id);
});

it('matches the email without case', function (): void {
    expect(loginChatterToAgentChat('Chatter@Example.TEST', 'chatter-pass')?->id)->toBe($this->chatter->id);
});

it('returns null when the password is wrong', function (): void {
    expect(loginChatterToAgentChat('chatter@example.test', 'wrong-password'))->toBeNull();
});

it('returns null when the chatter is deactivated, even with valid credentials', function (): void {
    app(DeactivateAccountAction::class)->execute($this->chatter, 'blocked by admin');

    expect(loginChatterToAgentChat('chatter@example.test', 'chatter-pass'))->toBeNull();
});

it('returns null when no chatter matches the email', function (): void {
    expect(loginChatterToAgentChat('no-such-user@example.test', 'chatter-pass'))->toBeNull();
});

it('still computes a password hash when no chatter matches the email', function (): void {
    // Same work as a wrong password for a real chatter, so response timing
    // can't reveal which emails are registered.
    Hash::shouldReceive('make')->once()->andReturn('hashed');

    expect(loginChatterToAgentChat('no-such-user@example.test', 'chatter-pass'))->toBeNull();
});

it('returns null for a chatter of this workspace without a grant for the agent', function (): void {
    Chatter::factory()->ofWorkspace($this->owner)->create([
        'email' => 'ungranted@example.test',
        'password' => Hash::make('pass'),
    ]);

    expect(loginChatterToAgentChat('ungranted@example.test', 'pass'))->toBeNull();
});

it('never finds a chatter of another workspace with the same email', function (): void {
    $otherAgent = createAgent(User::factory()->create());
    Chatter::factory()->grantedTo($otherAgent)->create([
        'email' => 'shared@example.test',
        'password' => Hash::make('elsewhere-pass'),
    ]);

    expect(loginChatterToAgentChat('shared@example.test', 'elsewhere-pass'))->toBeNull();
});

it('lets the same email be a chatter of two workspaces, each with its own password', function (): void {
    $here = Chatter::factory()->grantedTo($this->agent)->create([
        'email' => 'twice@example.test',
        'password' => Hash::make('here-pass'),
    ]);
    $otherAgent = createAgent(User::factory()->create());
    Chatter::factory()->grantedTo($otherAgent)->create([
        'email' => 'twice@example.test',
        'password' => Hash::make('there-pass'),
    ]);

    expect(loginChatterToAgentChat('twice@example.test', 'here-pass')?->id)->toBe($here->id)
        ->and(loginChatterToAgentChat('twice@example.test', 'there-pass'))->toBeNull();
});

it('never signs in a staff account, even with its right password', function (): void {
    expect(loginChatterToAgentChat('owner@example.test', 'correct-password'))->toBeNull();
});

it('returns null for a chatter without a password', function (): void {
    $this->chatter->forceFill(['password' => null])->save();

    expect(loginChatterToAgentChat('chatter@example.test', ''))->toBeNull();
});

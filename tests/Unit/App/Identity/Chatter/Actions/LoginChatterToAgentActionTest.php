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

function lctaLogin(string $email, string $password): ?Chatter
{
    return app(LoginChatterToAgentAction::class)->execute(
        new LoginData(email: $email, password: $password, remember: false),
        test()->agent,
    );
}

it('signs in a granted chatter by email without case, only with this workspace\'s password', function (): void {
    $here = Chatter::factory()->grantedTo($this->agent)->create([
        'email' => 'twice@example.test',
        'password' => Hash::make('here-pass'),
    ]);
    Chatter::factory()->grantedTo(createAgent(User::factory()->create()))->create([
        'email' => 'twice@example.test',
        'password' => Hash::make('there-pass'),
    ]);

    expect(lctaLogin('Chatter@Example.TEST', 'chatter-pass')?->id)->toBe($this->chatter->id)
        ->and(lctaLogin('twice@example.test', 'here-pass')?->id)->toBe($here->id)
        ->and(lctaLogin('twice@example.test', 'there-pass'))->toBeNull();
});

it('refuses a wrong password, a staff account, and an ungranted, passwordless or deactivated chatter', function (): void {
    Chatter::factory()->ofWorkspace($this->owner)->create([
        'email' => 'ungranted@example.test',
        'password' => Hash::make('pass'),
    ]);
    Chatter::factory()->grantedTo($this->agent)->create(['email' => 'nopass@example.test', 'password' => null]);
    $wrongPassword = lctaLogin('chatter@example.test', 'wrong-password');
    $staff = lctaLogin('owner@example.test', 'correct-password');
    $ungranted = lctaLogin('ungranted@example.test', 'pass');
    $passwordless = lctaLogin('nopass@example.test', '');
    app(DeactivateAccountAction::class)->execute($this->chatter, 'blocked by admin');

    $deactivated = lctaLogin('chatter@example.test', 'chatter-pass');

    expect($wrongPassword)->toBeNull()
        ->and($staff)->toBeNull()
        ->and($ungranted)->toBeNull()
        ->and($passwordless)->toBeNull()
        ->and($deactivated)->toBeNull();
});

it('still computes a password hash when no chatter matches the email', function (): void {
    Hash::shouldReceive('make')->once()->andReturn('hashed');

    expect(lctaLogin('no-such-user@example.test', 'chatter-pass'))->toBeNull();
});

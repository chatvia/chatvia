<?php

use App\Identity\Workspace\Actions\Account\RegisterUserAction;
use App\Identity\Workspace\Data\Account\RegisterUserData;
use App\Identity\Workspace\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;

it('creates a user and dispatches the Registered event', function (): void {
    Event::fake([Registered::class]);

    $user = app(RegisterUserAction::class)->execute(new RegisterUserData(
        name: 'Ada Lovelace',
        email: 'ada@example.test',
        password: 'secret-password',
        domain: 'example.test',
        registered_ip: '127.0.0.1',
    ));

    expect($user)->toBeInstanceOf(User::class)
        ->and($user->name)->toBe('Ada Lovelace')
        ->and($user->email)->toBe('ada@example.test')
        ->and($user->domain)->toBe('example.test')
        ->and($user->registered_ip)->toBe('127.0.0.1')
        ->and(Hash::check('secret-password', $user->password))->toBeTrue();

    Event::assertDispatched(Registered::class);
});

it('defers team, billing and quota provisioning to the trial-checkout webhook', function (): void {
    // UserObserver::created auto-provisioning is retired: registration only
    // creates the user row. The default team, UserBilling and UserQuota rows
    // are provisioned by HandleStripeWebhook when the trial checkout lands —
    // so a freshly registered user must have none of them yet.
    Event::fake([Registered::class]);

    $user = app(RegisterUserAction::class)->execute(new RegisterUserData(
        name: 'Grace Hopper',
        email: 'grace@example.test',
        password: 'compiler',
        domain: 'example.test',
        registered_ip: '127.0.0.1',
    ));

    expect($user->ownedTeams()->count())->toBe(0)
        ->and($user->current_team_id)->toBeNull()
        ->and($user->billing()->exists())->toBeFalse()
        ->and($user->quota()->exists())->toBeFalse();
});

it('hashes the password before storing it', function (): void {
    Event::fake([Registered::class]);

    $user = app(RegisterUserAction::class)->execute(new RegisterUserData(
        name: 'Linus',
        email: 'linus@example.test',
        password: 'PlainTextPassword',
        domain: 'example.test',
        registered_ip: '127.0.0.1',
    ));

    expect($user->password)->not->toBe('PlainTextPassword')
        ->and(Hash::check('PlainTextPassword', $user->password))->toBeTrue();
});

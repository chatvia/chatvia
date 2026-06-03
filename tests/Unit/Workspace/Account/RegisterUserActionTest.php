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

it('ensures a primary team is set up for the new user', function (): void {
    Event::fake([Registered::class]);

    $user = app(RegisterUserAction::class)->execute(new RegisterUserData(
        name: 'Grace Hopper',
        email: 'grace@example.test',
        password: 'compiler',
        domain: 'example.test',
        registered_ip: '127.0.0.1',
    ));

    expect($user->ownedTeams()->count())->toBeGreaterThan(0)
        ->and($user->primaryTeam())->not->toBeNull();
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

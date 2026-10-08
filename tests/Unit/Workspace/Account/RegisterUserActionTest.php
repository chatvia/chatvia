<?php

use App\Http\Requests\Auth\RegisterUserRequest;
use App\Identity\Workspace\Actions\Account\RegisterUserAction;
use App\Identity\Workspace\Data\Account\RegisterUserData;
use App\Identity\Workspace\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;

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
    // Registration only creates the user row. The default team, UserBilling
    // and UserQuota rows are provisioned by HandleStripeWebhook when the trial
    // checkout lands — so a freshly registered user must have none of them yet.
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

it('stores the country passed in the DTO without any outbound geo-IP call', function (): void {
    Event::fake([Registered::class]);
    Http::preventStrayRequests();

    $user = app(RegisterUserAction::class)->execute(new RegisterUserData(
        name: 'Ada',
        email: 'ada-country@example.test',
        password: 'secret-password',
        domain: 'example.test',
        registered_ip: '203.0.113.7',
        registered_country_code: 'de',
    ));

    expect($user->fresh()->registered_country_code)->toBe('de');
    Http::assertNothingSent();
});

it('dispatches Registered only after the transaction has committed', function (): void {
    $baseline = DB::transactionLevel();
    $levels = [];
    Event::listen(Registered::class, function () use (&$levels): void {
        $levels[] = DB::transactionLevel();
    });

    app(RegisterUserAction::class)->execute(new RegisterUserData(
        name: 'Grace',
        email: 'grace-commit@example.test',
        password: 'secret-password',
        domain: 'example.test',
        registered_ip: '127.0.0.1',
    ));

    expect($levels)->toBe([$baseline]);
});

it('reads the visitor country from the Cloudflare header, not from an IP lookup', function (): void {
    Http::preventStrayRequests();

    $request = RegisterUserRequest::create('/register', 'POST', [
        'name' => 'Ada',
        'email' => 'ada@example.test',
        'password' => 'secret-password',
    ], server: ['HTTP_CF_IPCOUNTRY' => 'DE', 'REMOTE_ADDR' => '203.0.113.7']);

    expect($request->toData()->registered_country_code)->toBe('de');
});

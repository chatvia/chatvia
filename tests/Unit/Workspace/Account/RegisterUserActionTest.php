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

it('creates a user who owns a new workspace and dispatches the Registered event', function (): void {
    Event::fake([Registered::class]);

    $user = app(RegisterUserAction::class)->execute(new RegisterUserData(
        name: 'Ada Lovelace',
        email: 'ada@example.test',
        password: 'secret-password',
        registered_ip: '127.0.0.1',
    ));

    expect($user)->toBeInstanceOf(User::class)
        ->and($user->name)->toBe('Ada Lovelace')
        ->and($user->email)->toBe('ada@example.test')
        ->and($user->isWorkspaceOwner())->toBeTrue()
        ->and($user->registered_ip)->toBe('127.0.0.1')
        ->and(Hash::check('secret-password', $user->password))->toBeTrue();

    Event::assertDispatched(Registered::class);
});

it('creates the default team so the account is usable straight away', function (): void {
    Event::fake([Registered::class]);

    $user = app(RegisterUserAction::class)->execute(new RegisterUserData(
        name: 'Grace Hopper',
        email: 'grace@example.test',
        password: 'compiler',
        registered_ip: '127.0.0.1',
    ));

    $team = $user->ownedTeams()->sole();

    expect($user->current_team_id)->toBe($team->id)
        ->and($team->roles()->where('code', 'owner')->exists())->toBeTrue();
});

it('gives a new workspace no message limit by default', function (): void {
    Event::fake([Registered::class]);
    config(['chatvia.message_limits.new_workspace' => 0]);

    $user = app(RegisterUserAction::class)->execute(new RegisterUserData(
        name: 'Edsger Dijkstra',
        email: 'edsger@example.test',
        password: 'shortest-path',
        registered_ip: '127.0.0.1',
    ));

    expect($user->workspace()->messageLimit()->exists())->toBeFalse();
});

it('gives a new workspace the monthly message limit set in the admin panel', function (): void {
    Event::fake([Registered::class]);
    config(['chatvia.message_limits.new_workspace' => 500]);

    $user = app(RegisterUserAction::class)->execute(new RegisterUserData(
        name: 'Barbara Liskov',
        email: 'barbara@example.test',
        password: 'substitution',
        registered_ip: '127.0.0.1',
    ));

    $limit = $user->workspace()->messageLimit()->sole();

    expect($limit->monthly_limit)->toBe(500)
        ->and($limit->messages_used)->toBe(0)
        ->and($limit->period_starts_at->equalTo(now()->startOfMonth()))->toBeTrue();
});

it('hashes the password before storing it', function (): void {
    Event::fake([Registered::class]);

    $user = app(RegisterUserAction::class)->execute(new RegisterUserData(
        name: 'Linus',
        email: 'linus@example.test',
        password: 'PlainTextPassword',
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

<?php

use App\Identity\Workspace\Actions\Account\RegisterUserAction;
use App\Identity\Workspace\Data\Account\RegisterUserData;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;

/**
 * Self-serve sign-up: the new account owns a new workspace with its default
 * team, gets the message limit the admin set for new workspaces (none by
 * default), and Registered fires only once everything is committed.
 */
it('creates a user who owns a new workspace and dispatches the Registered event', function (): void {
    Event::fake([Registered::class]);

    $user = app(RegisterUserAction::class)->execute(new RegisterUserData(
        name: 'Ada Lovelace',
        email: 'ada@example.test',
        password: 'secret-password',
        registered_ip: '203.0.113.7',
        registered_country_code: 'de',
    ));

    expect($user->name)->toBe('Ada Lovelace')
        ->and($user->email)->toBe('ada@example.test')
        ->and($user->isWorkspaceOwner())->toBeTrue()
        ->and($user->fresh()->registered_ip)->toBe('203.0.113.7')
        ->and($user->fresh()->registered_country_code)->toBe('de')
        ->and($user->password)->not->toBe('secret-password')
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
    $this->travelTo('2026-03-17 10:00:00');
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
        ->and($limit->period_starts_at->toDateTimeString())->toBe('2026-03-01 00:00:00');
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

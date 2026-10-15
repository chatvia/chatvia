<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/*
 * app:create-pulse-database runs before the migrations (Docker's migrate step,
 * db:fresh): it creates Pulse's own database when the server does not have it,
 * and leaves an existing one alone. The tests point the `pulse` connection at
 * the testing database (phpunit.xml).
 */

function databaseExists(string $name): bool
{
    return (bool) DB::scalar('select exists (select 1 from pg_database where datname = ?)', [$name]);
}

it('leaves an existing Pulse database alone', function (): void {
    $this->artisan('app:create-pulse-database')
        ->expectsOutputToContain('The Pulse database "testing" is there.')
        ->assertSuccessful();
});

it('creates the Pulse database when the server does not have it', function (): void {
    $name = 'pulse_test_'.Str::lower(Str::random(8));
    config(['database.connections.pulse.database' => $name]);
    DB::purge('pulse');

    try {
        $this->artisan('app:create-pulse-database')
            ->expectsOutputToContain("Created the Pulse database \"{$name}\".")
            ->assertSuccessful();

        expect(databaseExists($name))->toBeTrue();
    } finally {
        DB::purge('pulse');
        DB::connectUsing('pulse-cleanup', [...config('database.connections.pulse'), 'database' => 'postgres'], force: true)
            ->statement("drop database if exists \"{$name}\"");
        DB::purge('pulse-cleanup');
    }

    expect(databaseExists($name))->toBeFalse();
});

<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/*
 * app:create-tool-databases runs before the migrations (Docker's migrate
 * step, db:fresh): it creates the Pulse and Spectra databases when the server
 * does not have them, and leaves existing ones alone. The tests point the
 * `pulse` and `spectra` connections at the testing database (phpunit.xml).
 */

function ctdDatabaseExists(string $name): bool
{
    return (bool) DB::scalar('select exists (select 1 from pg_database where datname = ?)', [$name]);
}

it('leaves existing tool databases alone', function (): void {
    $this->artisan('app:create-tool-databases')
        ->expectsOutputToContain('The Pulse database "testing" is there.')
        ->expectsOutputToContain('The Spectra database "testing" is there.')
        ->assertSuccessful();
});

it('creates a tool database when the server does not have it', function (string $connection, string $tool): void {
    $name = "{$connection}_test_".Str::lower(Str::random(8));
    config(["database.connections.{$connection}.database" => $name]);
    DB::purge($connection);

    try {
        $this->artisan('app:create-tool-databases')
            ->expectsOutputToContain("Created the {$tool} database \"{$name}\".")
            ->assertSuccessful();

        expect(ctdDatabaseExists($name))->toBeTrue();
    } finally {
        DB::purge($connection);
        DB::connectUsing('tool-cleanup', [...config("database.connections.{$connection}"), 'database' => 'postgres'], force: true)
            ->statement("drop database if exists \"{$name}\"");
        DB::purge('tool-cleanup');
    }

    expect(ctdDatabaseExists($name))->toBeFalse();
})->with([
    'Pulse' => ['pulse', 'Pulse'],
    'Spectra' => ['spectra', 'Spectra'],
]);

<?php

use App\Admin\Backup\Exceptions\BackupException;
use App\Admin\Backup\Services\PostgresDump;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;

/**
 * Dumping a database: pg_dump in custom format, then a read-back check
 * that catches a cut-off dump; and which connections are databases of
 * their own that can be reached.
 */
it('dumps in custom format and reads the dump back', function (): void {
    Process::fake(["'pg_dump'*" => Process::result(), "'pg_restore'*" => Process::result()]);

    app(PostgresDump::class)->dump('pgsql', '/tmp/x.dump');

    Process::assertRan(fn ($process): bool => $process->command[0] === 'pg_dump'
        && $process->command[1] === '-Fc'
        && in_array(DB::connection('pgsql')->getConfig('database'), $process->command, true));
    Process::assertRan(fn ($process): bool => $process->command === ['pg_restore', '--list', '/tmp/x.dump']);
});

it('fails when the dump cannot be read back', function (): void {
    Process::fake([
        "'pg_dump'*" => Process::result(),
        "'pg_restore'*" => Process::result(errorOutput: 'unexpected end of file', exitCode: 1),
    ]);

    expect(fn () => app(PostgresDump::class)->dump('pgsql', '/tmp/x.dump'))
        ->toThrow(BackupException::class, 'cannot be read back: unexpected end of file');
});

it('tells a database of its own from the main one under another name, and one that cannot be reached', function (): void {
    config(['database.connections.analytics.database' => DB::connection('pgsql')->getConfig('database')]);
    DB::purge('analytics');

    expect(app(PostgresDump::class)->isSeparate('analytics'))->toBeFalse();

    config(['database.connections.analytics.database' => 'analytics_elsewhere']);
    DB::purge('analytics');

    expect(app(PostgresDump::class)->isSeparate('analytics'))->toBeTrue()
        ->and(app(PostgresDump::class)->exists('analytics'))->toBeFalse();

    DB::purge('analytics');
});

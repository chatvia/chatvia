<?php

use App\Admin\Backup\Actions\RunBackupAction;
use App\Admin\Backup\Enums\BackupPart;
use App\Admin\Backup\Enums\BackupStatus;
use App\Admin\Backup\Models\Backup;
use App\Admin\Backup\Services\PostgresDump;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Making a backup: the database and Redis in one tar in the layout of
 * `./manage backup`, stored on the private disk; encrypted to the public key
 * and only then with the app key; the uploaded files and the other databases
 * when they apply. A failed step, or a backup already running, saves the
 * failure and leaves no archive or work folder. pg_dump, redis-cli and gpg
 * are faked; tar runs for real.
 */
beforeEach(function (): void {
    Storage::fake('backups');
    config([
        'chatvia.backup.public_key' => null,
        'chatvia.backup.include_files' => false,
        'chatvia.backup.include_tool_databases' => true,
        'chatvia.backup.keep' => 14,
        'database.redis.default.password' => 'redis-secret',
    ]);
});

/**
 * Fakes pg_dump, pg_restore, redis-cli and gpg, each writing the file it
 * was asked for. gpg "encrypts" by copying, so the archive inside can still
 * be read.
 */
function rbaFakePrograms(bool $dumpFails = false): void
{
    $after = fn (PendingProcess $process, string $flag): string => $process->command[array_search($flag, $process->command, true) + 1];

    Process::fake([
        "'pg_dump'*" => function (PendingProcess $process) use ($after, $dumpFails) {
            if ($dumpFails) {
                return Process::result(errorOutput: 'connection refused', exitCode: 1);
            }

            File::put($after($process, '-f'), 'PGDMP '.$after($process, '-d'));

            return Process::result();
        },
        "'pg_restore'*" => Process::result('; Archive created'),
        "'redis-cli'*" => function (PendingProcess $process) use ($after) {
            File::put($after($process, '--rdb'), 'REDIS0012 snapshot');

            return Process::result();
        },
        "'gpg'*" => function (PendingProcess $process) use ($after) {
            if (in_array('show-only', $process->command, true)) {
                return Process::result(implode("\n", [
                    'pub:-:255:22:8E89D0F7F34C5DE7:1792998709:::-:::cEC:::::ed25519:::0:',
                    'fpr:::::::::B267A209E6AEC7A0993FA6038E89D0F7F34C5DE7:',
                    'uid:-::::1792998709::447381A22FCB2A091BB622BCCF120937A66EE4C7::ChatVia Backup Test <backup-test@example.com>::::::::::0:',
                ]));
            }

            if (in_array('--encrypt', $process->command, true)) {
                File::copy(Arr::last($process->command), $after($process, '--output'));
            }

            return Process::result();
        },
    ]);
}

function rbaRun(): Backup
{
    return app(RunBackupAction::class)->execute(Backup::factory()->create());
}

/**
 * The members of a stored archive, with their contents.
 *
 * @return array<string, string>
 */
function rbaMembers(Backup $backup): array
{
    // tar itself, not PharData: Phar caches an archive by its path, and two
    // backups made in the same second have the same name.
    $archive = Storage::disk('backups')->path((string) $backup->path);

    return Str::of(Process::run(['tar', '-tf', $archive])->throw()->output())
        ->trim()
        ->explode("\n")
        ->mapWithKeys(fn (string $member): array => [$member => Process::run(['tar', '-xOf', $archive, $member])->output()])
        ->all();
}

it('stores the database and Redis in one tar, as ./manage backup does', function (): void {
    rbaFakePrograms();

    $backup = rbaRun();

    expect($backup->status)->toBe(BackupStatus::Completed)
        ->and($backup->path)->toMatch('#^backups/chatvia-\d{4}-\d{2}-\d{2}-\d{6}\.tar$#')
        ->and($backup->disk)->toBe('backups')
        ->and($backup->parts->all())->toBe([BackupPart::Database, BackupPart::Redis])
        ->and($backup->isEncrypted())->toBeFalse()
        ->and($backup->sha256)->toBe(hash_file('sha256', Storage::disk('backups')->path($backup->path)))
        ->and($backup->size)->toBe(Storage::disk('backups')->size($backup->path))
        ->and($backup->started_at)->not->toBeNull()
        ->and($backup->finished_at)->not->toBeNull();

    $members = rbaMembers($backup);

    expect(array_keys($members))->toEqualCanonicalizing(['backup.env', 'database-extras.sql', 'database.dump', 'redis.rdb'])
        ->and($members['database.dump'])->toStartWith('PGDMP')
        ->and($members['redis.rdb'])->toStartWith('REDIS')
        ->and($members['backup.env'])->toContain("FORMAT=1\n", "HOLDS=database redis\n", 'LATEST_MIGRATION=2')
        ->and($members['database-extras.sql'])->toContain('what pg_dump leaves out');
});

it('passes the passwords in the environment, never on the command line', function (): void {
    rbaFakePrograms();

    rbaRun();

    $password = (string) DB::connection()->getConfig('password');

    Process::assertRan(fn (PendingProcess $process): bool => $process->command[0] === 'pg_dump'
        && ($process->environment['PGPASSWORD'] ?? '') === $password
        && (blank($password) || ! in_array($password, $process->command, true)));
    Process::assertRan(fn (PendingProcess $process): bool => $process->command[0] === 'redis-cli'
        && ($process->environment['REDISCLI_AUTH'] ?? null) === 'redis-secret'
        && ! in_array('redis-secret', $process->command, true));
});

it('encrypts the archive to the public key, and only then adds the app key', function (): void {
    rbaFakePrograms();
    config(['chatvia.backup.public_key' => File::get(base_path('tests/Fixtures/backup/public.asc'))]);

    $backup = rbaRun();

    expect($backup->status)->toBe(BackupStatus::Completed)
        ->and($backup->path)->toEndWith('.tar.gpg')
        ->and($backup->key_fingerprint)->toBe('B267A209E6AEC7A0993FA6038E89D0F7F34C5DE7')
        ->and($backup->parts->all())->toBe([BackupPart::Database, BackupPart::Secrets, BackupPart::Redis]);

    Process::assertRan(fn (PendingProcess $process): bool => in_array('--recipient', $process->command, true)
        && in_array('B267A209E6AEC7A0993FA6038E89D0F7F34C5DE7', $process->command, true));

    $members = rbaMembers($backup);

    expect($members['secrets.env'])->toContain('APP_KEY='.config('app.key'))
        ->and($members['backup.env'])->toContain("HOLDS=database secrets redis\n")
        ->and(Storage::disk('backups')->files('backups'))->toBe([$backup->path]);
});

it('adds the uploaded files on this server, without the backups', function (): void {
    rbaFakePrograms();
    $storage = deleteAfterTest(create_temp_dir('/rba-storage-'.Str::random(8)));
    File::ensureDirectoryExists($storage.'/app/private/backups');
    File::ensureDirectoryExists($storage.'/app/private/workspaces');
    File::put($storage.'/app/private/workspaces/knowledge.txt', 'knowledge');
    File::put($storage.'/app/private/backups/old.tar', 'an older backup');
    $previous = app()->storagePath();
    app()->useStoragePath($storage);
    config(['chatvia.backup.include_files' => true, 'filesystems.storage' => 'local']);

    try {
        $backup = rbaRun();
    } finally {
        app()->useStoragePath($previous);
    }

    expect($backup->parts->all())->toBe([BackupPart::Database, BackupPart::Redis, BackupPart::Files]);

    $listing = (string) Process::pipe([
        ['tar', '-xOf', Storage::disk('backups')->path((string) $backup->path), 'files.tar'],
        ['tar', '-tf', '-'],
    ])->output();

    expect($listing)->toContain('app/private/workspaces/knowledge.txt')
        ->not->toContain('old.tar');
});

it('leaves out the uploaded files when they are kept in S3 storage', function (): void {
    rbaFakePrograms();
    config(['chatvia.backup.include_files' => true, 'filesystems.storage' => 's3']);

    expect(rbaRun()->parts->all())->toBe([BackupPart::Database, BackupPart::Redis]);
});

it('dumps the analytics database when the analytics tables have their own, and, when asked, the Pulse and Spectra ones', function (bool $tools, array $parts): void {
    rbaFakePrograms();
    config(['chatvia.backup.include_tool_databases' => $tools, 'chatvia.analytics.connection' => 'analytics']);
    $this->partialMock(PostgresDump::class, function ($mock): void {
        $mock->shouldReceive('isSeparate')->andReturnUsing(fn (string $connection): bool => $connection !== config('database.default'));
        $mock->shouldReceive('exists')->andReturnTrue();
    });

    $backup = rbaRun();

    expect($backup->parts->all())->toBe($parts)
        ->and(array_keys(rbaMembers($backup)))->toContain(...array_map(fn (BackupPart $part): string => $part->member(), $parts));
})->with([
    'with the tool databases' => [true, [BackupPart::Database, BackupPart::Redis, BackupPart::Analytics, BackupPart::Pulse, BackupPart::Spectra]],
    'without them' => [false, [BackupPart::Database, BackupPart::Redis, BackupPart::Analytics]],
]);

it('saves a failed step on the backup and keeps no archive or work folder', function (): void {
    rbaFakePrograms(dumpFails: true);
    $backup = Backup::factory()->create();

    app(RunBackupAction::class)->execute($backup);

    expect($backup->status)->toBe(BackupStatus::Failed)
        ->and($backup->error)->toContain('pg_dump of the pgsql database failed: connection refused')
        ->and($backup->finished_at)->not->toBeNull()
        ->and(Storage::disk('backups')->allFiles())->toBe([])
        ->and(File::isDirectory(storage_path('framework/backups/'.$backup->external_id)))->toBeFalse();
});

it('fails at once while another backup is running', function (): void {
    rbaFakePrograms();
    $lock = Cache::lock('backups:run', 60);
    $lock->get();

    try {
        $backup = rbaRun();
    } finally {
        $lock->release();
    }

    expect($backup->status)->toBe(BackupStatus::Failed)
        ->and($backup->error)->toContain('Another backup was running');
    Process::assertNothingRan();
});

it('marks a backup left running by a stopped worker as failed', function (): void {
    rbaFakePrograms();
    $abandoned = Backup::factory()->create(['status' => BackupStatus::Running, 'started_at' => now()->subDay()]);

    rbaRun();

    expect($abandoned->fresh()->status)->toBe(BackupStatus::Failed)
        ->and($abandoned->fresh()->error)->toBe('Stopped before it finished.');
});

it('deletes the backups past the number to keep', function (): void {
    rbaFakePrograms();
    config(['chatvia.backup.keep' => 1]);
    $old = Backup::factory()->completed(now()->subDay())->create();
    Storage::disk('backups')->put($old->path, 'old');

    $backup = rbaRun();

    expect(Backup::query()->pluck('id')->all())->toBe([$backup->id])
        ->and(Storage::disk('backups')->exists($old->path))->toBeFalse();
});

it('leaves out the analytics database while the analytics tables are in the main one', function (): void {
    rbaFakePrograms();
    config(['chatvia.backup.include_tool_databases' => false, 'chatvia.analytics.connection' => null]);
    $this->partialMock(PostgresDump::class, function ($mock): void {
        $mock->shouldReceive('isSeparate')->andReturnUsing(fn (string $connection): bool => $connection !== config('database.default'));
        $mock->shouldReceive('exists')->andReturnTrue();
    });

    expect(rbaRun()->parts->all())->toBe([BackupPart::Database, BackupPart::Redis]);
});

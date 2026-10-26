<?php

declare(strict_types=1);

use App\Admin\Backup\Exceptions\BackupException;
use App\Admin\Backup\Services\RedisSnapshot;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

/**
 * The Redis snapshot: `redis-cli --rdb` against the app's Redis (REDIS_URL
 * first), the password in REDISCLI_AUTH, and a check that Redis sent an
 * RDB file.
 */
function rsPath(): string
{
    return deleteAfterTest(create_temp_dir('/rs-'.Str::random(8))).'/redis.rdb';
}

it('asks Redis for an RDB snapshot with the password in the environment', function (): void {
    config(['database.redis.default' => ['host' => 'redis', 'port' => '6380', 'username' => 'app', 'password' => 'secret']]);
    $path = rsPath();
    Process::fake(["'redis-cli'*" => function () use ($path) {
        File::put($path, 'REDIS0012');

        return Process::result();
    }]);

    app(RedisSnapshot::class)->dump($path);

    Process::assertRan(fn ($process): bool => $process->command === ['redis-cli', '-h', 'redis', '-p', '6380', '--user', 'app', '--rdb', $path]
        && $process->environment === ['REDISCLI_AUTH' => 'secret']);
});

it('reads the server from REDIS_URL, with TLS for rediss', function (): void {
    config(['database.redis.default' => ['url' => 'rediss://:url-secret@cache.example.com:6390', 'host' => '127.0.0.1']]);
    $path = rsPath();
    Process::fake(["'redis-cli'*" => function () use ($path) {
        File::put($path, 'REDIS0012');

        return Process::result();
    }]);

    app(RedisSnapshot::class)->dump($path);

    Process::assertRan(fn ($process): bool => $process->command === ['redis-cli', '-h', 'cache.example.com', '-p', '6390', '--tls', '--rdb', $path]
        && $process->environment === ['REDISCLI_AUTH' => 'url-secret']);
});

it('fails when Redis sends no RDB file', function (): void {
    $path = rsPath();
    Process::fake(["'redis-cli'*" => function () use ($path) {
        File::put($path, '-ERR');

        return Process::result();
    }]);

    expect(fn () => app(RedisSnapshot::class)->dump($path))->toThrow(BackupException::class, 'Redis sent no RDB snapshot.');
});

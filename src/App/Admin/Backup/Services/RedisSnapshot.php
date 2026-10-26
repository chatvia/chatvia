<?php

declare(strict_types=1);

namespace App\Admin\Backup\Services;

use App\Admin\Backup\Exceptions\BackupException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Illuminate\Support\Uri;
use SplFileObject;

/**
 * A snapshot of all Redis data (every database: queues, cache, chat memory,
 * sign-ins) as an RDB file, made by Redis itself: `redis-cli --rdb` asks the
 * server for one as a replica would, so its own files stay as they are. The
 * same file `./manage backup` saves as `redis.rdb`; `./manage restore` loads
 * it and then empties the queues and the cache.
 *
 * The password goes to redis-cli through REDISCLI_AUTH, never on its command
 * line.
 */
class RedisSnapshot
{
    private const int TIMEOUT_SECONDS = 3600;

    public function dump(string $path): void
    {
        $server = $this->server();

        $result = Process::timeout(self::TIMEOUT_SECONDS)
            ->env(array_filter(['REDISCLI_AUTH' => $server['password']], filled(...)))
            ->run([
                (string) config('services.bin.redis_cli'),
                '-h', $server['host'],
                '-p', $server['port'],
                ...(filled($server['username']) ? ['--user', $server['username']] : []),
                ...($server['tls'] ? ['--tls'] : []),
                '--rdb', $path,
            ]);

        if (! $result->successful()) {
            throw new BackupException('redis-cli --rdb failed: '.Str::trim($result->errorOutput() ?: $result->output()));
        }

        // An RDB file starts with "REDIS".
        if (! File::isFile($path) || new SplFileObject($path)->fread(5) !== 'REDIS') {
            throw new BackupException('Redis sent no RDB snapshot.');
        }
    }

    /**
     * The server of the app's `default` Redis connection; REDIS_URL wins
     * over the host and port, as in Laravel.
     *
     * @return array{host: string, port: string, username: string, password: string, tls: bool}
     */
    private function server(): array
    {
        $config = (array) config('database.redis.default');
        $url = (string) ($config['url'] ?? '');
        $uri = filled($url) ? Uri::of($url) : null;

        return [
            'host' => (string) ($uri?->host() ?: ($config['host'] ?? '127.0.0.1')),
            'port' => (string) ($uri?->port() ?: ($config['port'] ?? '6379')),
            'username' => (string) ($uri?->user() ?: ($config['username'] ?? '')),
            'password' => (string) ($uri?->password() ?: ($config['password'] ?? '')),
            'tls' => $uri?->scheme() === 'rediss' || ($config['scheme'] ?? null) === 'tls',
        ];
    }
}

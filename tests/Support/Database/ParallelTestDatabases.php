<?php

declare(strict_types=1);

namespace Tests\Support\Database;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Filesystem\LockableFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Sleep;
use Throwable;

/**
 * Gives each worker of a parallel run (`--parallel`) its own database, a copy
 * of the testing database.
 *
 * Building every worker's database with the migrations at the same time runs
 * PostgreSQL out of locks (`max_locks_per_transaction`). So one worker at a
 * time, under a file lock, brings the testing database up to date
 * ({@see ProvisionTestDatabase}), then copies it with `CREATE DATABASE …
 * TEMPLATE`, which runs no migrations. A worker's copy is made again only
 * when its schema checksum no longer matches. PostgreSQL copies a database
 * only while nothing is connected to it, so every connection of this
 * process is closed first; if another session still has it open, the
 * worker builds its own database from the migrations instead.
 *
 * Laravel runs it for each worker in the main process, before the workers
 * start (`ParallelTesting::setUpProcess()` in AppServiceProvider), so no
 * test has the testing database open yet. The file lock keeps two runs on
 * the same machine from copying at once.
 */
final class ParallelTestDatabases
{
    /** The connections that point at the testing database in the tests (phpunit.xml). */
    public const array CONNECTIONS_ON_THE_TESTING_DATABASE = ['pulse', 'spectra'];

    private const int COPY_ATTEMPTS = 5;

    /** The worker's database, named the way Laravel names it: `{testing}_test_{token}`. */
    public static function workerDatabase(string $database, string $token): string
    {
        return "{$database}_test_{$token}";
    }

    public static function prepare(Repository $config, string $token): void
    {
        $connection = (string) $config->get('database.default');
        $template = (string) $config->get("database.connections.{$connection}.database");
        $worker = self::workerDatabase($template, $token);

        $lockPath = storage_path('framework/testing/parallel-databases.lock');
        File::ensureDirectoryExists(dirname($lockPath));
        $lock = new LockableFile($lockPath, 'c');
        $lock->getExclusiveLock(block: true);

        try {
            // The testing database itself is up to date with the migration files.
            (new ProvisionTestDatabase)();
            $checksum = DB::table('test_schema_checksum')->value('checksum');
            self::disconnectAll();

            if (self::checksumOf($config, $connection, $worker) !== $checksum) {
                self::copy($config, $connection, $template, $worker);
            }
        } finally {
            self::disconnectAll();
            $lock->releaseLock()->close();
        }
    }

    /** Close every connection this process holds, so none keeps the testing database open. */
    private static function disconnectAll(): void
    {
        foreach (array_keys(DB::getConnections()) as $name) {
            DB::purge($name);
        }
    }

    /** The schema checksum stored in $database, or null when it has none or does not exist. */
    private static function checksumOf(Repository $config, string $connection, string $database): ?string
    {
        return self::using($config, $connection, $database, fn (): ?string => rescue(
            fn (): ?string => DB::connection('parallel-testing')->table('test_schema_checksum')->value('checksum'),
            report: false,
        ));
    }

    private static function copy(Repository $config, string $connection, string $template, string $worker): void
    {
        // A database is dropped and copied from another one: `postgres`.
        for ($attempt = 1; $attempt <= self::COPY_ATTEMPTS; $attempt++) {
            $copied = self::using($config, $connection, 'postgres', function () use ($template, $worker): bool {
                DB::connection('parallel-testing')->statement("DROP DATABASE IF EXISTS \"{$worker}\"");
                DB::connection('parallel-testing')->statement("CREATE DATABASE \"{$worker}\" TEMPLATE \"{$template}\"");

                return true;
            });

            if ($copied === true) {
                return;
            }

            Sleep::for($attempt)->seconds();
        }

        self::build($config, $connection, $worker);
    }

    /**
     * Build the worker's database from the migrations, with the Pulse and
     * Spectra connections on it too, so their tables land in it.
     */
    private static function build(Repository $config, string $connection, string $worker): void
    {
        self::using($config, $connection, 'postgres', fn (): bool => DB::connection('parallel-testing')->statement("CREATE DATABASE \"{$worker}\""));

        $names = [$connection, ...self::CONNECTIONS_ON_THE_TESTING_DATABASE];
        $original = collect($names)->mapWithKeys(fn (string $name): array => [$name => $config->get("database.connections.{$name}.database")]);

        foreach ($names as $name) {
            $config->set("database.connections.{$name}.database", $worker);
        }

        self::disconnectAll();

        try {
            (new ProvisionTestDatabase)();
        } finally {
            foreach ($original as $name => $database) {
                $config->set("database.connections.{$name}.database", $database);
            }

            self::disconnectAll();
        }
    }

    /**
     * Run $callback with a `parallel-testing` connection: the default one,
     * pointed at $database. Null when the callback throws.
     *
     * @template TResult
     *
     * @param  callable(): TResult  $callback
     * @return TResult|null
     */
    private static function using(Repository $config, string $connection, string $database, callable $callback): mixed
    {
        $config->set('database.connections.parallel-testing', [...$config->get("database.connections.{$connection}"), 'database' => $database]);

        try {
            return $callback();
        } catch (Throwable) {
            return null;
        } finally {
            DB::purge('parallel-testing');
        }
    }
}

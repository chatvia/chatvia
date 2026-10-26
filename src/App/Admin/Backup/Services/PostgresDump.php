<?php

declare(strict_types=1);

namespace App\Admin\Backup\Services;

use App\Admin\Backup\Exceptions\BackupException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Throwable;

/**
 * Dumps one PostgreSQL database of the install with `pg_dump` (custom
 * format, compressed), as `./manage backup` does, and checks the dump can be
 * read back. Also writes the SQL for what a dump leaves out: the database's
 * own settings (`ALTER DATABASE … SET`), which `./manage restore` runs with
 * `psql -v dbname=<new database>`.
 *
 * The password goes to pg_dump through PGPASSWORD, never on its command
 * line, where other users of the server could see it.
 */
class PostgresDump
{
    /** A large database takes long to dump. */
    private const int TIMEOUT_SECONDS = 4 * 3600;

    /** Dumps the database of $connection (config/database.php) to $path. */
    public function dump(string $connection, string $path): void
    {
        $config = $this->config($connection);

        $dump = Process::timeout(self::TIMEOUT_SECONDS)
            ->env($this->environment($config))
            ->run([
                (string) config('services.bin.pg_dump'),
                '-Fc',
                '-h', (string) ($config['host'] ?? '127.0.0.1'),
                '-p', (string) ($config['port'] ?? '5432'),
                '-U', (string) ($config['username'] ?? ''),
                '-d', (string) ($config['database'] ?? ''),
                '-f', $path,
            ]);

        if (! $dump->successful()) {
            throw new BackupException("pg_dump of the {$connection} database failed: ".Str::trim($dump->errorOutput()));
        }

        // Reads the whole table of contents back: a cut-off dump fails here.
        $check = Process::timeout(self::TIMEOUT_SECONDS)->run([(string) config('services.bin.pg_restore'), '--list', $path]);

        if (! $check->successful()) {
            throw new BackupException("The dump of the {$connection} database cannot be read back: ".Str::trim($check->errorOutput()));
        }
    }

    /**
     * The `ALTER DATABASE :"dbname" SET …` lines for the settings of the
     * database of $connection, the same as `dump_extras` in docker/manage.
     */
    public function settingsSql(string $connection): string
    {
        /** @var Collection<int, object{line: string}> $rows */
        $rows = collect(DB::connection($connection)->select(<<<'SQL'
            SELECT format('ALTER DATABASE :"dbname" SET %I = %s;', name,
                   CASE WHEN name IN ('search_path', 'temp_tablespaces', 'session_preload_libraries',
                                      'local_preload_libraries', 'shared_preload_libraries', 'unix_socket_directories')
                        THEN value ELSE quote_literal(value) END) AS line
            FROM (SELECT split_part(setting, '=', 1) AS name, substr(setting, strpos(setting, '=') + 1) AS value
                  FROM pg_db_role_setting s, unnest(s.setconfig) AS setting
                  WHERE s.setrole = 0
                    AND s.setdatabase = (SELECT oid FROM pg_database WHERE datname = current_database())) AS settings
            SQL));

        return $rows
            ->map(fn (object $row): string => $row->line)
            ->prepend('-- Written by ChatVia.ai on '.now()->format('Y-m-d H:i:s').": what pg_dump leaves out.\n")
            ->implode("\n")."\n";
    }

    /**
     * Whether $connection names a database of its own, not the app's main
     * one under another name (the analytics tables stay in the main
     * database unless DB_ANALYTICS_DATABASE moves them).
     */
    public function isSeparate(string $connection): bool
    {
        $main = $this->config((string) config('database.default'));
        $other = $this->config($connection);

        return [$other['host'] ?? null, (string) ($other['port'] ?? ''), $other['database'] ?? null]
            !== [$main['host'] ?? null, (string) ($main['port'] ?? ''), $main['database'] ?? null];
    }

    /** Whether the database of $connection is there and can be reached. */
    public function exists(string $connection): bool
    {
        try {
            DB::connection($connection)->select('SELECT 1');

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * The connection's settings, with a DB_URL already taken apart.
     *
     * @return array<string, mixed>
     */
    private function config(string $connection): array
    {
        return DB::connection($connection)->getConfig();
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, string>
     */
    private function environment(array $config): array
    {
        return array_filter([
            'PGPASSWORD' => (string) ($config['password'] ?? ''),
            'PGSSLMODE' => (string) ($config['sslmode'] ?? ''),
        ], filled(...));
    }
}

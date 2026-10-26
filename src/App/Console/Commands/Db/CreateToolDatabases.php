<?php

declare(strict_types=1);

namespace App\Console\Commands\Db;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;

/**
 * Start-up and deploy step, run before the migrations: creates the databases
 * that sit next to the app's when the server does not have them yet. They are
 * the analytics tables' (the `analytics` connection, DB_ANALYTICS_DATABASE),
 * Pulse's (`pulse`, DB_PULSE_DATABASE) and Spectra's (`spectra`,
 * DB_SPECTRA_DATABASE). `migrate` then creates their tables in them.
 *
 * It connects to the server's `postgres` database, which every PostgreSQL
 * server has, the same way `migrate` creates a missing app database. The
 * database user needs the CREATEDB right; without it, an operator creates the
 * databases once by hand.
 */
#[Description('Create the analytics, Pulse and Spectra databases (DB_ANALYTICS_DATABASE, DB_PULSE_DATABASE, DB_SPECTRA_DATABASE) when the PostgreSQL server does not have them yet.')]
#[Signature('chatvia:db:create-tool-databases')]
class CreateToolDatabases extends Command
{
    /** Each database connection, with the name of what keeps its data there. */
    public const array CONNECTIONS = [
        'analytics' => 'analytics',
        'pulse' => 'Pulse',
        'spectra' => 'Spectra',
    ];

    private const string SERVER_CONNECTION = 'tool-database-server';

    public function handle(DatabaseManager $databases): int
    {
        $created = true;

        foreach (self::CONNECTIONS as $connection => $tool) {
            $created = $this->ensure($databases, $connection, $tool) && $created;
        }

        return $created ? self::SUCCESS : self::FAILURE;
    }

    private function ensure(DatabaseManager $databases, string $connection, string $tool): bool
    {
        $database = $databases->connection($connection);
        $name = $database->getDatabaseName();

        $databases->connectUsing(self::SERVER_CONNECTION, [...$database->getConfig(), 'database' => 'postgres'], force: true);
        $server = $databases->connection(self::SERVER_CONNECTION);

        try {
            if ($server->scalar('select exists (select 1 from pg_database where datname = ?)', [$name])) {
                $this->components->info("The {$tool} database \"{$name}\" is there.");

                return true;
            }

            $server->getSchemaBuilder()->createDatabase($name);
        } catch (QueryException $exception) {
            $this->components->error("Could not create the {$tool} database \"{$name}\": {$exception->getPrevious()?->getMessage()}");
            $this->line('Create it yourself (CREATE DATABASE "'.$name.'" OWNER "'.$database->getConfig('username').'"), or give the');
            $this->line('database user the CREATEDB right. DB_'.Str::upper($connection).'_DATABASE names it.');

            return false;
        } finally {
            $databases->purge(self::SERVER_CONNECTION);
        }

        $this->components->info("Created the {$tool} database \"{$name}\".");

        return true;
    }
}

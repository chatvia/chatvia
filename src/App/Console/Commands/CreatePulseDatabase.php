<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\QueryException;

/**
 * Start-up and deploy step, run before the migrations: creates Pulse's own
 * database (the `pulse` connection, DB_PULSE_DATABASE) when the server does
 * not have it yet. `migrate` then creates Pulse's tables in it.
 *
 * It connects to the server's `postgres` database, which every PostgreSQL
 * server has, the same way `migrate` creates a missing app database. The
 * database user needs the CREATEDB right; without it, an operator creates the
 * database once by hand.
 */
#[Description('Create the Pulse database (DB_PULSE_DATABASE) when the PostgreSQL server does not have it yet.')]
#[Signature('app:create-pulse-database')]
class CreatePulseDatabase extends Command
{
    private const string SERVER_CONNECTION = 'pulse-server';

    public function handle(DatabaseManager $databases): int
    {
        $pulse = $databases->connection('pulse');
        $name = $pulse->getDatabaseName();

        $databases->connectUsing(self::SERVER_CONNECTION, [...$pulse->getConfig(), 'database' => 'postgres'], force: true);
        $server = $databases->connection(self::SERVER_CONNECTION);

        try {
            if ($server->scalar('select exists (select 1 from pg_database where datname = ?)', [$name])) {
                $this->components->info("The Pulse database \"{$name}\" is there.");

                return self::SUCCESS;
            }

            $server->getSchemaBuilder()->createDatabase($name);
        } catch (QueryException $exception) {
            $this->components->error("Could not create the Pulse database \"{$name}\": {$exception->getPrevious()?->getMessage()}");
            $this->line('Create it yourself (CREATE DATABASE "'.$name.'" OWNER "'.$pulse->getConfig('username').'"), or give the');
            $this->line('database user the CREATEDB right. DB_PULSE_DATABASE names it.');

            return self::FAILURE;
        } finally {
            $databases->purge(self::SERVER_CONNECTION);
        }

        $this->components->info("Created the Pulse database \"{$name}\".");

        return self::SUCCESS;
    }
}

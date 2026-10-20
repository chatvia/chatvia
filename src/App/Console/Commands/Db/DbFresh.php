<?php

declare(strict_types=1);

namespace App\Console\Commands\Db;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * `php artisan chatvia:db:fresh` — `migrate:fresh` for every database the app uses:
 * the main schema plus the Pulse and Spectra databases (created first when
 * missing), which a plain `migrate:fresh` does not reach. The Postgres
 * extensions stay installed through the wipe; the install migration
 * recreates any that are missing.
 *
 * Mirrors the logic in tests/RefreshTestDatabases.php — kept in sync there.
 */
#[Description('Drop every table in all app databases, then re-migrate. Pass --seed to also run seeders.')]
#[Signature('chatvia:db:fresh {--seed : Run seeders after migrating}')]
class DbFresh extends Command
{
    public function handle(): int
    {
        $this->components->info('Wiping the database');
        $this->call('db:wipe', ['--force' => true]);

        // Pulse and Spectra keep their tables in databases of their own,
        // which the wipe above does not reach.
        $this->components->info('Wiping the Pulse and Spectra databases');
        if ($this->call('chatvia:db:create-tool-databases') !== self::SUCCESS) {
            return self::FAILURE;
        }

        foreach (array_keys(CreateToolDatabases::CONNECTIONS) as $connection) {
            $this->call('db:wipe', ['--database' => $connection, '--force' => true]);
        }

        $this->components->info('Running migrate');
        $this->call('migrate', ['--force' => true]);

        if ($this->option('seed')) {
            $this->components->info('Seeding');
            $this->call('db:seed', ['--force' => true]);
        }

        return self::SUCCESS;
    }
}

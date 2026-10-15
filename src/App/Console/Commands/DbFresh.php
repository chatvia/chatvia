<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * `php artisan db:fresh` — the version of `migrate:fresh` that actually
 * works against our PostgreSQL schema.
 *
 * Plain `migrate:fresh` fails because `pg_tokenizer`, `vchord`, and
 * `vchord_bm25` own tables and views in our database; `DROP TABLE ... CASCADE`
 * refuses to remove them and the wipe aborts halfway. The fix is to drop
 * those extensions first (which cleanly removes everything they planted),
 * run the standard migration cycle, and reinstall the extensions afterwards
 * so the schema is ready to use. Pulse's own database is wiped too (and
 * created first when it is missing).
 *
 * Mirrors the logic in tests/RefreshTestDatabases.php — kept in sync there.
 */
#[Description('Drop everything (extensions + tables), re-migrate, and reinstall Postgres extensions. Pass --seed to also run seeders.')]
#[Signature('db:fresh {--seed : Run seeders after migrating}')]
class DbFresh extends Command
{
    /**
     * Extensions that own catalog tables / views in our schema. Order matters
     * on the way DOWN: `vchord_bm25` and `vchord` depend on `vector`, so they
     * must be dropped first. On the way UP we go in dependency order with
     * `vector` first.
     */
    private const array EXTENSIONS_DOWN = ['vchord_bm25', 'vchord', 'pg_tokenizer'];

    private const array EXTENSIONS_UP = ['vector', 'pg_trgm', 'pg_tokenizer', 'vchord', 'vchord_bm25'];

    public function handle(): int
    {
        // Order: drop extensions to free their catalog tables/views → wipe →
        // reinstall extensions so the migrations can use their types → migrate.
        // Some migrations reference types like `bm25_catalog.bm25vector`, so the
        // extension must exist BEFORE migrate runs.

        $this->components->info('Dropping Postgres extensions that block db:wipe');
        foreach (self::EXTENSIONS_DOWN as $extension) {
            $this->components->task($extension, fn () => DB::statement("DROP EXTENSION IF EXISTS {$extension} CASCADE"));
        }

        $this->components->info('Wiping the database');
        $this->call('db:wipe', ['--force' => true]);

        // Pulse keeps its tables in a database of its own, which the wipe
        // above does not reach.
        $this->components->info('Wiping the Pulse database');
        if ($this->call('app:create-pulse-database') !== self::SUCCESS) {
            return self::FAILURE;
        }

        $this->call('db:wipe', ['--database' => 'pulse', '--force' => true]);

        $this->components->info('Reinstalling Postgres extensions');
        foreach (self::EXTENSIONS_UP as $extension) {
            $this->components->task($extension, fn () => DB::statement("CREATE EXTENSION IF NOT EXISTS {$extension} CASCADE"));
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

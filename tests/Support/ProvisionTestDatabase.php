<?php

namespace Tests\Support;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Finder\Finder;

/**
 * Brings the CURRENT default database up to date with the migration files.
 *
 * The suite runs against persistent, seeded Postgres databases (the schema is
 * never rebuilt per test — DatabaseTransactions isolates tests instead), and
 * migrations are edited in place pre-release, so a database can silently drift
 * from the files. A checksum of database/ (excluding factories/seeders) is
 * stored INSIDE each database — a shared file can't say which of the parallel
 * worker databases (testing_test_1, _test_2, ...) was rebuilt.
 *
 * Called from RefreshTestDatabases::beginDatabaseTransaction() — once per
 * process, before the first test transaction opens. (The suite runs serially;
 * `--parallel` is unsupported because each worker database would need this
 * same healing wired into Laravel's parallel-testing bootstrap.)
 */
class ProvisionTestDatabase
{
    public function __invoke(): void
    {
        // A missing schema, a missing checksum, or a checksum mismatch all
        // mean this particular database needs (re)building.
        if (! $this->databaseAlreadyMigrated()) {
            // Fresh DB: install extensions then run migrate. We do NOT call
            // migrate:fresh because db:wipe drops tables across all schemas
            // (including extension catalogs) which leaves the extension types
            // in a half-broken state for subsequent CREATE TABLE calls.
            // Seeding is required: factories resolve the seeded demo user.
            $this->installPostgresExtensions();
            Artisan::call('migrate', ['--seed' => true]);
            $this->recordChecksum();

            return;
        }

        if ($this->storedChecksum() !== $this->calculateChecksum()) {
            // Rebuild from zero. Order matters twice over: the extension
            // catalogs must be gone BEFORE the wipe (db:wipe cannot drop
            // extension-owned tables like tokenizer_catalog.*), and the
            // extensions must be back BEFORE migrate (the schema uses their
            // types). That rules out migrate:fresh, which wipes and migrates
            // in one step.
            $this->dropPostgresExtensions();
            Artisan::call('db:wipe');
            $this->installPostgresExtensions();
            Artisan::call('migrate', ['--seed' => true]);
            // Record the checksum only after a successful rebuild — writing it
            // first would mark a failed migration as up to date and skip every
            // retry.
            $this->recordChecksum();
        }
    }

    protected function databaseAlreadyMigrated(): bool
    {
        try {
            return Schema::hasTable('migrations') && DB::table('migrations')->exists();
        } catch (\Throwable) {
            return false;
        }
    }

    /** The schema checksum this database was last (re)built against, if any. */
    protected function storedChecksum(): ?string
    {
        try {
            return DB::table('test_schema_checksum')->value('checksum');
        } catch (\Throwable) {
            return null;
        }
    }

    protected function recordChecksum(): void
    {
        if (! Schema::hasTable('test_schema_checksum')) {
            Schema::create('test_schema_checksum', function ($table): void {
                $table->string('checksum');
            });
        }

        DB::table('test_schema_checksum')->delete();
        DB::table('test_schema_checksum')->insert(['checksum' => $this->calculateChecksum()]);
    }

    protected function calculateChecksum(): string
    {
        $files = Finder::create()
            ->files()
            ->exclude([
                'factories',
                'seeders',
            ])
            ->in(database_path())
            ->ignoreDotFiles(true)
            ->ignoreVCS(true)
            ->getIterator();

        $files = array_keys(iterator_to_array($files));

        $checksum = collect($files)->map(fn ($file): string|false => md5_file($file))->implode('');

        return md5($checksum);
    }

    protected function dropPostgresExtensions(): void
    {
        $extensions = [
            'pg_tokenizer',
            'vchord',
            'vchord_bm25',
        ];

        foreach ($extensions as $extension) {
            rescue(fn () => DB::statement("DROP EXTENSION IF EXISTS {$extension} CASCADE"));
        }
    }

    /**
     * Install Postgres extensions required by the schema. Order matters:
     * `vector` first (required by vchord), then the rest. The migrations
     * reference these schemas/types but don't create the extensions.
     */
    protected function installPostgresExtensions(): void
    {
        $extensions = [
            'vector',
            'pg_trgm',
            'pg_tokenizer',
            'vchord',
            'vchord_bm25',
        ];

        foreach ($extensions as $extension) {
            rescue(fn () => DB::statement("CREATE EXTENSION IF NOT EXISTS {$extension} CASCADE"));
        }
    }
}

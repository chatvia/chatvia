<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Finder\Finder;

trait RefreshTestDatabases
{
    use DatabaseTransactions;

    protected function refreshTestDatabase(): void
    {
        if (! RefreshDatabaseState::$migrated) {
            $this->runMigrationsIfNecessary();

            $this->app[Kernel::class]->setArtisan(null);

            RefreshDatabaseState::$migrated = true;
        }

        $this->beginDatabaseTransaction();
    }

    protected function runMigrationsIfNecessary(): void
    {
        // In parallel mode each worker gets its own database (testing_test_1,
        // _test_2, ...). The checksum file is shared but the DB is fresh, so
        // we must also force migration when the current DB has no schema.
        $databaseEmpty = ! $this->databaseAlreadyMigrated();

        if ($databaseEmpty) {
            // Fresh per-worker DB: install extensions then run migrate. We do
            // NOT call migrate:fresh because db:wipe drops tables across all
            // schemas (including extension catalogs) which leaves the extension
            // types in a half-broken state for subsequent CREATE TABLE calls.
            $this->installPostgresExtensions();
            $this->artisan('migrate');
            $this->createChecksum();

            return;
        }

        if (! $this->identicalChecksum()) {
            $this->createChecksum();
            $this->dropPostgresExtensions();
            $this->installPostgresExtensions();
            $this->artisan('migrate:fresh');
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

        $checksum = collect($files)->map(fn ($file) => md5_file($file))->implode('');

        return md5($checksum);
    }

    protected function checksumFilePath(): string
    {
        return base_path('.database-checksum.txt');
    }

    protected function createChecksum(): void
    {
        file_put_contents($this->checksumFilePath(), $this->calculateChecksum());
    }

    protected function checksumFileContents(): bool|string
    {
        return file_get_contents($this->checksumFilePath());
    }

    protected function isChecksumExists(): bool
    {
        return file_exists($this->checksumFilePath());
    }

    protected function identicalChecksum(): bool
    {
        if ($this->isChecksumExists() === false) {
            return false;
        }

        if ($this->checksumFileContents() === $this->calculateChecksum()) {
            return true;
        }

        return false;
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

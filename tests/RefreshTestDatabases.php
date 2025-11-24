<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
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
        if ($this->identicalChecksum() === false) {
            $this->createChecksum();
            $this->dropPostgresExtensions();
            $this->artisan('migrate:fresh');
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
}

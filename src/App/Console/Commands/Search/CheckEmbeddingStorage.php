<?php

declare(strict_types=1);

namespace App\Console\Commands\Search;

use Domain\Knowledge\Embedding\Data\EmbeddingColumnData;
use Domain\Knowledge\Embedding\Queries\StoredEmbeddingColumnsQuery;
use Domain\Knowledge\Embedding\Services\EmbeddingStorage;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Start-up and deploy step, run after the migrations: fails when
 * EMBEDDING_VECTOR_TYPE or EMBEDDING_DIMENSIONS no longer match the embedding
 * columns in the database. They are chosen once, at install; a later change
 * would break every search and every new embedding, so the app stops here
 * with a clear message instead.
 */
#[Description('Check that the embedding columns in the database match EMBEDDING_VECTOR_TYPE and EMBEDDING_DIMENSIONS.')]
#[Signature('chatvia:search:check-embedding-storage')]
class CheckEmbeddingStorage extends Command
{
    public function handle(EmbeddingStorage $storage, StoredEmbeddingColumnsQuery $columns): int
    {
        $expected = $storage->columnType();
        $stored = $columns->get();

        if ($stored->isEmpty()) {
            $this->info("No embedding columns yet. New ones will be {$expected}.");

            return self::SUCCESS;
        }

        $mismatches = $stored->reject(fn (EmbeddingColumnData $column): bool => $column->type === $expected);

        if ($mismatches->isEmpty()) {
            $this->info("Embedding storage matches the settings: {$expected}.");

            return self::SUCCESS;
        }

        $this->error("The settings say {$expected}, but the database stores embeddings differently:");

        foreach ($mismatches as $column) {
            $this->line("  {$column->table}.{$column->column}: {$column->type}");
        }

        $this->newLine();
        $this->line('EMBEDDING_VECTOR_TYPE and EMBEDDING_DIMENSIONS are chosen once, at install, and cannot change');
        $this->line('afterwards. Set them back to the values the database was created with.');

        return self::FAILURE;
    }
}

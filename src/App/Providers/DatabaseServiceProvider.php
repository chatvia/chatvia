<?php

namespace App\Providers;

use Domain\Knowledge\Embedding\Services\EmbeddingStorage;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\ServiceProvider;

/**
 * Teaches the schema builder the `embedding` column: a pgvector `vector` /
 * `halfvec` column with the install's vector type and size (EmbeddingStorage).
 */
class DatabaseServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Blueprint::macro('embedding', function (string $column) {
            $storage = app(EmbeddingStorage::class);

            return $this->addColumn($storage->type()->value, $column, ['dimensions' => $storage->dimensions()]);
        });
    }
}

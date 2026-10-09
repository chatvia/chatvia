<?php

namespace App\Providers;

use Illuminate\Console\Events\CommandStarting;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Grammars\PostgresGrammar;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * Teaches the schema builder about the Postgres extensions the knowledge base
 * needs (the `bm25vector` column type from VectorChord-BM25) and keeps
 * `db:wipe` / `migrate:fresh` working while the pg_tokenizer extension owns its
 * own tables.
 */
class DatabaseServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        PostgresGrammar::macro('typeBm25vector', fn (): string => 'bm25_catalog.bm25vector');

        Blueprint::macro('bm25vector', fn (string $column) => $this->addColumn('bm25vector', $column));

        $this->dropTokenizerExtensionBeforeWipe();
    }

    /**
     * `db:wipe` (and therefore `migrate:fresh`) aborts because the
     * pg_tokenizer extension owns the tokenizer_catalog.* tables and Postgres
     * refuses to drop them with the rest. Drop the extension up front; the
     * install_vectorchord_extensions migration recreates it (and the
     * llmlingua2 tokenizer) before any later migration needs it.
     */
    private function dropTokenizerExtensionBeforeWipe(): void
    {
        Event::listen(CommandStarting::class, function (CommandStarting $event): void {
            if (! in_array($event->command, ['migrate:fresh', 'db:wipe'], true)) {
                return;
            }

            // CommandStarting fires before ConfirmableTrait prompts; in
            // production without --force the command may still be aborted,
            // so don't pre-drop the extension there.
            if ($this->app->isProduction() && ! $event->input->hasParameterOption('--force')) {
                return;
            }

            $database = $event->input->getParameterOption('--database', null);
            $connection = DB::connection($database === false ? null : $database);

            if ($connection->getDriverName() === 'pgsql') {
                $connection->statement('DROP EXTENSION IF EXISTS pg_tokenizer CASCADE');
            }
        });
    }
}

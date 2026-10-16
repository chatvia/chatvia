<?php

declare(strict_types=1);

namespace Domain\Knowledge\Retrieval\Actions;

use Domain\Knowledge\Retrieval\Enums\LexicalDriver;
use Domain\Knowledge\Retrieval\Services\LexicalSearch;
use Illuminate\Support\Facades\DB;

/**
 * Creates the lexical-search indexes in the shape of the install's driver
 * (LexicalSearch): pg_textsearch `bm25` indexes, or GIN expression indexes for
 * the built-in full-text search. The index names are fixed — queries reference
 * them (`to_bm25query`) — so switching the driver or the text configuration
 * means re-running `php artisan search:lexical-indexes`. The create migrations
 * call this per table, after their table exists.
 */
final readonly class ConfigureLexicalIndexesAction
{
    /** Every lexical index: table => [index name => text column]. */
    public const array INDEXES = [
        'chunks' => [
            'chunks_search_text_lexical' => 'search_text',
            'chunks_questions_lexical' => 'questions',
        ],
        'connector_tools' => ['connector_tools_search_text_lexical' => 'search_text'],
        'mcp_tools' => ['mcp_tools_search_text_lexical' => 'search_text'],
        'functions' => ['functions_search_text_lexical' => 'search_text'],
        'form_definitions' => ['form_definitions_search_text_lexical' => 'search_text'],
    ];

    public function __construct(private LexicalSearch $lexical) {}

    public function execute(?string $table = null): void
    {
        foreach (self::INDEXES as $tableName => $indexes) {
            if ($table !== null && $table !== $tableName) {
                continue;
            }

            foreach ($indexes as $index => $column) {
                $this->sync($tableName, $index, $column);
            }
        }
    }

    private function sync(string $table, string $index, string $column): void
    {
        $config = $this->lexical->textConfig();

        DB::statement("DROP INDEX IF EXISTS {$index}");
        DB::statement(match ($this->lexical->driver()) {
            LexicalDriver::PgTextsearch => "CREATE INDEX {$index} ON {$table} USING bm25 ({$column}) WITH (text_config='{$config}')",
            LexicalDriver::Postgres => "CREATE INDEX {$index} ON {$table} USING gin (to_tsvector('{$config}', {$column}))",
        });
    }
}

<?php

declare(strict_types=1);

use Domain\Knowledge\Retrieval\Actions\ConfigureLexicalIndexesAction;
use Domain\Knowledge\Retrieval\Services\LexicalSearch;
use Illuminate\Support\Facades\DB;

/**
 * The action swaps a table's lexical indexes to the configured driver's shape
 * under their fixed names (the queries reference the names). DDL here runs
 * inside the test transaction, so each test leaves the database as it was.
 */
function cliaRun(string $driver, string $table): void
{
    config(['chatvia.search.lexical_driver' => $driver]);

    (new ConfigureLexicalIndexesAction(new LexicalSearch))->execute($table);
}

function cliaIndexDefinition(string $index): string
{
    return (string) DB::scalar('SELECT indexdef FROM pg_indexes WHERE indexname = ?', [$index]);
}

it('builds GIN expression indexes for the built-in driver', function (): void {
    cliaRun('postgres', 'form_definitions');

    expect(cliaIndexDefinition('form_definitions_search_text_lexical'))
        ->toContain('USING gin')
        ->toContain("to_tsvector('simple'");
});

it('builds bm25 indexes for the pg_textsearch driver', function (): void {
    $installed = (bool) DB::scalar("SELECT EXISTS (SELECT 1 FROM pg_extension WHERE extname = 'pg_textsearch')");

    if (! $installed) {
        $this->markTestSkipped('pg_textsearch is not installed in this database.');
    }

    cliaRun('pg_textsearch', 'form_definitions');

    expect(cliaIndexDefinition('form_definitions_search_text_lexical'))
        ->toContain('USING bm25')
        ->toContain('text_config=simple');
});

it('covers every search_text column the hybrid searches read', function (): void {
    expect(array_keys(ConfigureLexicalIndexesAction::INDEXES))
        ->toBe(['chunks', 'connector_tools', 'mcp_tools', 'functions', 'form_definitions'])
        ->and(ConfigureLexicalIndexesAction::INDEXES['chunks'])
        ->toBe([
            'chunks_search_text_lexical' => 'search_text',
            'chunks_questions_lexical' => 'questions',
        ]);
});

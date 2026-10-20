<?php

declare(strict_types=1);

use Domain\Knowledge\Retrieval\Enums\PgTextsearchState;
use Domain\Knowledge\Retrieval\Services\PgTextsearchSupport;
use Illuminate\Support\Facades\DB;

/**
 * The state follows the database: not on the server, on it but not loaded,
 * loaded but not created, or created (and used unless SEARCH_LEXICAL_DRIVER
 * keeps the built-in search).
 */
it('reads how ready pg_textsearch is from the database', function (): void {
    $row = DB::selectOne("SELECT installed_version FROM pg_available_extensions WHERE name = 'pg_textsearch'");
    config(['chatvia.search.lexical_driver' => 'auto']);

    $state = app(PgTextsearchSupport::class)->state();

    match (true) {
        $row === null => expect($state)->toBe(PgTextsearchState::NotInstalled),
        $row->installed_version !== null => expect($state)->toBe(PgTextsearchState::Active),
        default => expect($state)->toBeIn([PgTextsearchState::ReadyToEnable, PgTextsearchState::NotPreloaded]),
    };
});

it('says pg_textsearch is switched off when the driver keeps the built-in search', function (): void {
    if (! DB::scalar("SELECT EXISTS (SELECT 1 FROM pg_extension WHERE extname = 'pg_textsearch')")) {
        $this->markTestSkipped('pg_textsearch is not installed in this database.');
    }

    config(['chatvia.search.lexical_driver' => 'postgres']);

    expect(app(PgTextsearchSupport::class)->state())->toBe(PgTextsearchState::SwitchedOff);
});

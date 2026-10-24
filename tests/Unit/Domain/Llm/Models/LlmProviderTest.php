<?php

declare(strict_types=1);

use Domain\Llm\Enums\LlmProviderKind;
use Domain\Llm\Models\LlmProvider;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * The llm_providers table guards its rows itself: only a server of your own
 * keeps a key and an address on its row (an account's key lives in Settings),
 * and the kind column takes every LlmProviderKind.
 */
it('lets only a server of your own keep a key on its row', function (): void {
    // In a savepoint, so the refused insert leaves the test's transaction usable.
    expect(fn (): LlmProvider => DB::transaction(fn (): LlmProvider => LlmProvider::factory()->kind(LlmProviderKind::Scaleway)->create(['api_key' => 'scw-key'])))
        ->toThrow(QueryException::class, 'llm_providers_key_on_custom_only');

    expect(LlmProvider::factory()->create(['api_key' => 'local-key'])->fresh()->api_key)->toBe('local-key');
});

it('saves every provider kind past the CHECK constraint on llm_providers.kind', function (LlmProviderKind $kind): void {
    // The column is an enum, which Postgres keeps as a CHECK constraint built
    // from LlmProviderKind when the table was created: a kind added later
    // cannot be saved until the schema is built again.
    expect(LlmProvider::factory()->kind($kind)->create()->fresh()->kind)->toBe($kind);
})->with(LlmProviderKind::cases());

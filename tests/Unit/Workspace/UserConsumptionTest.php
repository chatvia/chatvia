<?php

use App\Identity\Workspace\Models\User;

/**
 * The knowledge-consumption queries (pagesConsumed/minutesConsumed/linksConsumed)
 * build multi-join agent_sources subqueries by hand. This pins their execution
 * so the extraction of the shared subquery into a private helper stays
 * behaviour-preserving — a fresh account owns no sources, so each returns 0.
 */
it('returns zero consumption for an account that owns no sources', function (): void {
    $user = User::factory()->create();

    expect($user->pagesConsumed())->toBe(0)
        ->and($user->minutesConsumed())->toBe(0)
        ->and($user->linksConsumed())->toBe(0);
});

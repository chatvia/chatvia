<?php

use App\Identity\Workspace\Models\User;

/**
 * The knowledge-consumption queries (pages/minutes/links) build multi-join
 * agent_sources subqueries by hand. This pins their execution so the extraction
 * of the shared subquery into WorkspaceConsumption's private helper stays
 * behaviour-preserving — a fresh account owns no sources, so each returns 0.
 */
it('returns zero consumption for an account that owns no sources', function (): void {
    $consumption = User::factory()->create()->consumption();

    expect($consumption->pages())->toBe(0)
        ->and($consumption->minutes())->toBe(0)
        ->and($consumption->links())->toBe(0);
});

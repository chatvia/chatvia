<?php

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Knowledge\Source\Text\Models\SourceText;
use Illuminate\Support\Str;

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

/**
 * Regression: agent_sources.sourceable_type stores the enforced morph alias
 * ('source_text'), and the consumption queries used to compare it against the
 * FQCN — matching zero rows, so pages()/minutes()/links() were permanently 0
 * and the page-quota gate never tripped.
 */
it('counts pages for sources attached through the morph-aliased pivot', function (): void {
    $agent = Agent::factory()->create();

    $source = SourceText::create([
        'user_id' => $agent->user_id,
        'filepath' => 'users/'.$agent->user_id.'/agents/'.$agent->id.'/train/text/'.Str::uuid()->toString().'.txt',
        'excerpt' => 'quota regression',
        'length' => 5,
        'checksum' => md5('quota regression '.Str::uuid()->toString()),
    ]);

    $agent->texts()->attach($source->id);

    expect($agent->user->consumption()->pages())->toBe(5);
});

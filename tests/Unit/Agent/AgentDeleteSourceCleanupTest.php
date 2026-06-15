<?php

use Domain\Agent\Models\Agent;
use Domain\Agent\Models\AgentSource;
use Domain\Knowledge\Source\Text\Models\SourceText;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Regression: AgentObserver::deleting passes FQCNs to exclusiveSourceIds()
 * while agent_sources.sourceable_type stores morph aliases — the lookup
 * matched zero rows, so exclusively-owned sources (rows, storage, chunks)
 * survived agent deletion forever. exclusiveSourceIds() now resolves an FQCN
 * to its morph alias before comparing.
 */
function cleanupSourceText(Agent $agent): SourceText
{
    return SourceText::create([
        'user_id' => $agent->user_id,
        'filepath' => 'users/'.$agent->workspaceExternalId().'/agents/'.$agent->external_id.'/train/text/'.Str::uuid()->toString().'.txt',
        'excerpt' => 'cleanup',
        'length' => 1,
        'checksum' => md5(Str::uuid()->toString()),
    ]);
}

it('resolves exclusive source ids from an FQCN', function (): void {
    $agent = Agent::factory()->create();
    $source = cleanupSourceText($agent);
    $agent->texts()->attach($source->id);

    expect(AgentSource::exclusiveSourceIds($agent->id, SourceText::class)->all())
        ->toBe([$source->id]);
});

it('deletes exclusively-owned sources when the agent is deleted, keeps shared ones', function (): void {
    Storage::fake();

    $agent = Agent::factory()->create();
    $other = Agent::factory()->create(['user_id' => $agent->user_id]);

    $exclusive = cleanupSourceText($agent);
    $agent->texts()->attach($exclusive->id);

    $shared = cleanupSourceText($agent);
    $agent->texts()->attach($shared->id);
    $other->texts()->attach($shared->id);

    $agent->delete();

    expect(SourceText::find($exclusive->id))->toBeNull()
        ->and(SourceText::find($shared->id))->not->toBeNull();
});

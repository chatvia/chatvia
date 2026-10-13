<?php

use Domain\Agent\Actions\Account\DeleteAgentAction;
use Domain\Agent\Models\Agent;
use Domain\Agent\Models\AgentSource;
use Domain\Knowledge\Source\Services\KnowledgeStoragePath;
use Domain\Knowledge\Source\Text\Models\SourceText;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Regression: agent deletion passes FQCNs to exclusiveSourceIds() while
 * agent_sources.sourceable_type stores morph aliases — the lookup matched zero
 * rows, so exclusively-owned sources (rows, storage, chunks) survived agent
 * deletion forever. exclusiveSourceIds() now resolves an FQCN to its morph
 * alias before comparing.
 */
function cleanupSourceText(Agent $agent): SourceText
{
    $text = new SourceText(['workspace_id' => $agent->workspace_id, 'external_id' => (string) Str::uuid7()]);

    $text->fill([
        'filepath' => KnowledgeStoragePath::forSource($text).'/'.Str::uuid()->toString().'.txt',
        'excerpt' => 'cleanup',
        'length' => 1,
        'checksum' => md5(Str::uuid()->toString()),
    ])->save();

    return $text;
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
    $other = Agent::factory()->create(['workspace_id' => $agent->workspace_id]);

    $exclusive = cleanupSourceText($agent);
    $agent->texts()->attach($exclusive->id);

    $shared = cleanupSourceText($agent);
    $agent->texts()->attach($shared->id);
    $other->texts()->attach($shared->id);

    Storage::put($exclusive->filepath, 'exclusive');
    Storage::put($shared->filepath, 'shared');

    app(DeleteAgentAction::class)->execute($agent);

    expect(SourceText::find($exclusive->id))->toBeNull()
        ->and(SourceText::find($shared->id))->not->toBeNull();

    // The shared text was added through the deleted agent, but its file is in
    // the text's own folder, so the other agent keeps it.
    Storage::assertMissing($exclusive->filepath);
    Storage::assertExists($shared->filepath);
});

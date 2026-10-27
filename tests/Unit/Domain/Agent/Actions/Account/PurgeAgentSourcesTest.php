<?php

use Database\Factories\AgentFileFactory;
use Domain\Agent\Actions\Account\PurgeAgentAction;
use Domain\Agent\Models\Agent;
use Domain\Knowledge\Source\File\Models\SourceFile;
use Domain\Knowledge\Source\Services\KnowledgeStoragePath;
use Domain\Knowledge\Source\Text\Models\SourceText;
use Domain\Knowledge\Source\Web\Models\SourceWebsite;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * Which knowledge sources the purge of a deleted agent takes with it: the
 * ones no live agent of the workspace uses any more, of all four kinds. A
 * source another live agent still uses stays, with its stored files.
 */
beforeEach(function (): void {
    Storage::fake();
});

/** A source of one of the four kinds, in the workspace of the given agents and used by all of them. */
function pasSource(string $kind, Agent ...$agents): Model
{
    $workspace = ['workspace_id' => $agents[0]->workspace_id];

    [$source, $relation] = match ($kind) {
        'file' => [AgentFileFactory::new()->create($workspace), 'files'],
        'website' => [SourceWebsite::factory()->ingested()->create($workspace), 'websites'],
        'text' => [SourceText::factory()->create($workspace), 'texts'],
    };

    foreach ($agents as $agent) {
        $agent->{$relation}()->attach($source->getKey());
    }

    return $source;
}

it('deletes the agent own source of each kind and keeps one another agent uses', function (string $kind): void {
    $agent = Agent::factory()->create();
    $other = Agent::factory()->create(['workspace_id' => $agent->workspace_id]);

    $own = pasSource($kind, $agent);
    $shared = pasSource($kind, $agent, $other);

    $agent->delete();
    app(PurgeAgentAction::class)->execute($agent);

    expect($own::query()->find($own->getKey()))->toBeNull()
        ->and($shared::query()->find($shared->getKey()))->not->toBeNull();
})->with(['file', 'website', 'text']);

it('deletes a source of each kind shared only with another deleted agent', function (string $kind): void {
    $agent = Agent::factory()->create();
    $other = Agent::factory()->create(['workspace_id' => $agent->workspace_id]);

    $shared = pasSource($kind, $agent, $other);

    // Both agents were deleted; the other one's purge has not run yet.
    $other->delete();
    $agent->delete();
    app(PurgeAgentAction::class)->execute($agent);

    expect($shared::query()->find($shared->getKey()))->toBeNull();
})->with(['file', 'website', 'text']);

it('keeps the files of a source another agent still uses', function (): void {
    $agent = Agent::factory()->create();
    $other = Agent::factory()->create(['workspace_id' => $agent->workspace_id]);

    // The first agent uploaded it, then the other agent started using it too.
    $shared = pasSource('file', $agent, $other);
    $folder = KnowledgeStoragePath::forSource($shared);
    $shared->update(['parser_filepath' => $folder.'/pages.json']);
    Storage::put($shared->filepath, '%PDF-1.7');
    Storage::put($shared->parser_filepath, '[]');
    Storage::put($folder.'/images/page.jpg', 'jpeg');

    $agent->delete();
    app(PurgeAgentAction::class)->execute($agent);

    expect(SourceFile::find($shared->id))->not->toBeNull()
        ->and($other->files()->whereKey($shared->id)->exists())->toBeTrue();
    Storage::assertExists([$shared->filepath, $shared->parser_filepath, $folder.'/images/page.jpg']);
});

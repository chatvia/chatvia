<?php

use App\Identity\Workspace\Models\User;
use Database\Factories\AgentFileFactory;
use Domain\Knowledge\Ingestion\Jobs\Text\TextIngestionJob;
use Domain\Knowledge\Ingestion\Models\Chunk;
use Domain\Knowledge\Source\Enums\SourceStatus;
use Domain\Knowledge\Source\Text\Models\SourceText;
use Domain\Knowledge\Source\Web\Models\SourceWebsite;
use Domain\Membership\Team\Services\TeamService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;

/**
 * The agent's file, text and website lists come one page at a time (newest
 * first) and read the rows only: a text's content stays in storage, and only
 * a source that is training counts its chunks for the progress bar. Saving a
 * text the workspace already has is a validation error, not a server error.
 */
beforeEach(function (): void {
    $this->owner = User::factory()->create();
    app(TeamService::class)->createDefaultTeam($this->owner);
    $this->owner->refresh();

    $this->agent = createAgent($this->owner);
    $this->workspaceId = $this->owner->workspace()->id;
});

function slChunk(Model $source, int $workspaceId): void
{
    Chunk::query()->create([
        'workspace_id' => $workspaceId,
        'chunkable_type' => $source->getMorphClass(),
        'chunkable_id' => $source->getKey(),
        'chunk_index' => 1,
        'chunk' => 'A chunk.',
        'tokens' => 2,
        'embedding' => '['.implode(',', array_fill(0, 1024, 0.1)).']',
        'search_text' => 'A chunk.',
        'checksum' => md5(uniqid()),
        'cost_in_micro_cents' => 0,
    ]);
}

function slText(int $workspaceId, array $attributes = []): SourceText
{
    return SourceText::query()->create([
        'workspace_id' => $workspaceId,
        'filepath' => 'users/x/train/text/'.uniqid().'.txt',
        'excerpt' => 'An excerpt.',
        'length' => 1,
        'checksum' => md5(uniqid()),
        'status' => SourceStatus::Completed,
        ...$attributes,
    ]);
}

it('pages the file list, newest first, split into waiting and trained files', function (): void {
    $files = AgentFileFactory::new()->count(51)->create(['workspace_id' => $this->workspaceId, 'status' => 'ingested']);
    $uploaded = AgentFileFactory::new()->create(['workspace_id' => $this->workspaceId, 'status' => 'uploaded']);
    $this->agent->files()->attach([...$files->pluck('id')->all(), $uploaded->id]);

    $this->actingAs($this->owner)
        ->getJson(route('agents.sources.files.data', $this->agent))
        ->assertOk()
        ->assertJsonPath('total', 51)
        ->assertJsonPath('per_page', 50)
        ->assertJsonCount(50, 'data')
        ->assertJsonPath('data.0.id', $files->last()->id)
        ->assertJsonPath('data.0.progress', 100);

    $this->actingAs($this->owner)
        ->getJson(route('agents.sources.files.data', [$this->agent, 'page' => 2]))
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $files->first()->id);

    $this->actingAs($this->owner)
        ->getJson(route('agents.sources.files.data', [$this->agent, 'stage' => 'uploaded']))
        ->assertJsonPath('total', 1)
        ->assertJsonPath('data.0.id', $uploaded->id);
});

it('shows the progress of a training file from its stored chunks', function (): void {
    $file = AgentFileFactory::new()->create(['workspace_id' => $this->workspaceId, 'status' => 'ingesting', 'expected_chunks' => 4]);
    $this->agent->files()->attach($file->id);
    slChunk($file, $this->workspaceId);

    $this->actingAs($this->owner)
        ->getJson(route('agents.sources.files.data', $this->agent))
        ->assertJsonPath('data.0.progress', 25);
});

it('pages the text list from the rows, without reading the texts from storage', function (): void {
    Storage::fake();
    $texts = collect(range(1, 51))->map(fn (): SourceText => slText($this->workspaceId));
    $training = slText($this->workspaceId, ['status' => SourceStatus::Processing, 'expected_chunks' => 2]);
    slChunk($training, $this->workspaceId);
    $this->agent->texts()->attach([...$texts->pluck('id')->all(), $training->id]);

    $response = $this->actingAs($this->owner)
        ->getJson(route('agents.sources.text.index', $this->agent))
        ->assertOk()
        ->assertJsonPath('total', 52)
        ->assertJsonCount(50, 'data')
        ->assertJsonPath('data.0.id', $training->id)
        ->assertJsonPath('data.0.progress', 50)
        ->assertJsonPath('data.0.agents_count', 1);

    expect($response->json('data.0'))->not->toHaveKeys(['filepath', 'word_count']);
});

it('pages the website list with link counts and progress', function (): void {
    $websites = collect(range(1, 51))->map(fn (int $n): SourceWebsite => SourceWebsite::query()->create([
        'workspace_id' => $this->workspaceId,
        'url' => "https://docs{$n}.example.test",
        'status' => 'ingested',
    ]));
    $this->agent->websites()->attach($websites->pluck('id')->all());
    $websites->last()->urls()->create(['url' => 'https://docs51.example.test/a']);

    $this->actingAs($this->owner)
        ->getJson(route('agents.sources.websites.index', $this->agent))
        ->assertOk()
        ->assertJsonPath('total', 51)
        ->assertJsonCount(50, 'data')
        ->assertJsonPath('data.0.id', $websites->last()->id)
        ->assertJsonPath('data.0.urls_count', 1)
        ->assertJsonPath('data.0.progress', 100);
});

it('sets how often a website is refreshed', function (): void {
    $website = SourceWebsite::query()->create([
        'workspace_id' => $this->workspaceId,
        'url' => 'https://docs.example.test',
        'status' => 'ingested',
    ]);
    $this->agent->websites()->attach($website->id);

    $this->actingAs($this->owner)
        ->patchJson(route('agents.sources.websites.update', [$this->agent, $website]), ['refresh_interval' => 'yearly'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('refresh_interval');

    $this->actingAs($this->owner)
        ->patchJson(route('agents.sources.websites.update', [$this->agent, $website]), ['refresh_interval' => 'weekly'])
        ->assertOk();

    $this->actingAs($this->owner)
        ->getJson(route('agents.sources.websites.index', $this->agent))
        ->assertJsonPath('data.0.refresh_interval', 'weekly');
});

it('answers a text the workspace already has with a validation error', function (): void {
    Bus::fake([TextIngestionJob::class]);
    Storage::fake();
    $content = str_repeat('Our refund policy covers every purchase made in the last thirty days. ', 10);
    $other = createAgent($this->owner);

    $this->actingAs($this->owner)
        ->postJson(route('agents.sources.text.store', $other), ['content' => $content])
        ->assertOk();

    $this->actingAs($this->owner)
        ->postJson(route('agents.sources.text.store', $this->agent), ['content' => $content])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['content' => 'This text is already in your knowledge base.']);
});

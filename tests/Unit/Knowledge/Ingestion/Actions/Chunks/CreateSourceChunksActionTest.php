<?php

use Database\Factories\AgentFileFactory;
use Domain\Agent\Models\Agent;
use Domain\Knowledge\Embedding\Contracts\EmbeddingsProviderInterface;
use Domain\Knowledge\Ingestion\Actions\Chunks\CreateSourceChunksAction;
use Domain\Knowledge\Ingestion\Data\Chunking\ChunkPagesData;
use Domain\Knowledge\Ingestion\Data\Chunking\PageGroupData;
use Domain\Knowledge\Ingestion\Data\Ocr\PageData;
use Domain\Knowledge\Ingestion\Enums\ChunkEnhancement;
use Domain\Knowledge\Ingestion\Models\Chunk;
use Domain\Knowledge\Source\File\Models\SourceFile;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Support\FakeEmbeddingsProvider;

/**
 * Stores pages of a source as chunks: one embedding request and one insert
 * per group, pages the source already holds are skipped before anything is
 * paid for, and an enhanced page is written once, with its enhancement.
 */
function cscPage(int $number, string $content): PageData
{
    return new PageData(pageNumber: $number, content: $content, checksum: md5($content));
}

/**
 * @param  list<PageData>  $pages
 */
function cscData(SourceFile $file, array $pages, ChunkEnhancement $enhancement = ChunkEnhancement::Off, ?PageData $previous = null): ChunkPagesData
{
    return new ChunkPagesData(
        source: $file,
        group: new PageGroupData($pages, previousPage: $previous),
        enhancement: $enhancement,
    );
}

/** Keeps the texts sent to the embeddings provider from now on. */
function cscCountingEmbeddings(): FakeEmbeddingsProvider
{
    $provider = new FakeEmbeddingsProvider;

    app()->instance(EmbeddingsProviderInterface::class, $provider);

    return $provider;
}

function cscFakeChunker(): void
{
    Http::fake(fn (Request $request) => Http::response(['choices' => [['message' => ['content' => json_encode([
        'summary' => 'A page about oceans.',
        'questions' => ['Which ocean is largest?', 'How deep is it?'],
    ])]]], 'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 20]]));
}

it('stores a group of pages with one embedding request and one insert', function (): void {
    $embeddings = cscCountingEmbeddings();
    $agent = Agent::factory()->create();
    $file = AgentFileFactory::new()->create(['workspace_id' => $agent->workspace_id]);

    $inserts = 0;
    DB::listen(function (QueryExecuted $query) use (&$inserts): void {
        $inserts += str_starts_with($query->sql, 'insert into "chunks"') ? 1 : 0;
    });

    $created = app(CreateSourceChunksAction::class)->execute(cscData($file, [
        cscPage(3, 'The mitochondria is the powerhouse of the cell.'),
        cscPage(4, 'Chloroplasts perform photosynthesis in plant cells.'),
    ]));

    $chunk = Chunk::query()->findOrFail($created[md5('The mitochondria is the powerhouse of the cell.')]);

    expect($created)->toHaveCount(2)
        ->and($inserts)->toBe(1)
        ->and($embeddings->texts)->toHaveCount(2)
        ->and($chunk->chunk)->toBe('The mitochondria is the powerhouse of the cell.')
        ->and($chunk->chunk_index)->toBe(3)
        ->and($chunk->workspace_id)->toBe($agent->workspace_id)
        ->and($chunk->chunkable_type)->toBe((new SourceFile)->getMorphClass())
        ->and($chunk->chunkable_id)->toBe($file->id)
        ->and($chunk->is_enhanced)->toBeFalse()
        ->and($chunk->chunked_using_model)->toBeNull()
        ->and($chunk->external_id)->not->toBeNull()
        ->and($chunk->tokens)->toBeGreaterThan(0);
});

it('stores chunk text as plaintext at rest', function (): void {
    $agent = Agent::factory()->create();
    $file = AgentFileFactory::new()->create(['workspace_id' => $agent->workspace_id]);

    $created = app(CreateSourceChunksAction::class)->execute(cscData($file, [cscPage(1, 'Plain words at rest.')]));

    expect(DB::table('chunks')->where('id', $created->first())->value('chunk'))->toBe('Plain words at rest.');
});

it('skips pages the source holds and repeated pages without embedding them', function (): void {
    $agent = Agent::factory()->create();
    $file = AgentFileFactory::new()->create(['workspace_id' => $agent->workspace_id]);
    $first = app(CreateSourceChunksAction::class)->execute(cscData($file, [cscPage(1, 'Already stored.')]));

    $embeddings = cscCountingEmbeddings();

    $created = app(CreateSourceChunksAction::class)->execute(cscData($file, [
        cscPage(1, 'Already stored.'),
        cscPage(2, 'A new page.'),
        cscPage(3, 'A new page.'),
    ]));
    $rerun = app(CreateSourceChunksAction::class)->execute(cscData($file, [cscPage(1, 'Already stored.')]));

    expect($first)->toHaveCount(1)
        ->and($created->keys()->all())->toBe([md5('A new page.')])
        ->and($rerun)->toBeEmpty()
        ->and($embeddings->texts)->toBe(['A new page.'])
        ->and($file->chunks()->count())->toBe(2)
        ->and($file->chunks()->where('checksum', md5('A new page.'))->value('chunk_index'))->toBe(2);
});

it('enhances a page before storing it, so the chunk row is written once', function (): void {
    cscFakeChunker();
    $model = testLlm('enhance-model');
    config(['chatvia.ai.tasks.enhancement' => $model->external_name]);
    $embeddings = cscCountingEmbeddings();
    $agent = Agent::factory()->create();
    $file = AgentFileFactory::new()->create(['workspace_id' => $agent->workspace_id]);

    $updates = 0;
    DB::listen(function (QueryExecuted $query) use (&$updates): void {
        $updates += str_starts_with($query->sql, 'update "chunks"') ? 1 : 0;
    });

    app(CreateSourceChunksAction::class)->execute(cscData($file, [cscPage(1, 'The Pacific is the largest ocean.')], ChunkEnhancement::Text));

    $chunk = $file->chunks()->sole();

    expect($updates)->toBe(0)
        ->and($chunk->is_enhanced)->toBeTrue()
        ->and($chunk->chunked_using_model)->toBe($model->external_name)
        ->and($chunk->summary)->toBe('A page about oceans.')
        ->and($chunk->questions)->toBe("Which ocean is largest?\nHow deep is it?")
        ->and($chunk->questions_embedding)->not->toBeNull()
        ->and($chunk->chunk)->toBe('The Pacific is the largest ocean.')
        // The summary is embedded with the page; the questions get their own vector.
        ->and($embeddings->texts)->toBe([
            "A page about oceans.\n\nThe Pacific is the largest ocean.",
            "Which ocean is largest?\nHow deep is it?",
        ]);

    Http::assertSentCount(1);
});

it('gives a document page its neighbours as context, and a text page none', function (): void {
    cscFakeChunker();
    $agent = Agent::factory()->create();
    $file = AgentFileFactory::new()->create(['workspace_id' => $agent->workspace_id]);
    $previous = cscPage(1, 'Neighbour page about tides.');

    app(CreateSourceChunksAction::class)->execute(cscData($file, [cscPage(2, 'Document page.')], ChunkEnhancement::Document, $previous));
    app(CreateSourceChunksAction::class)->execute(cscData($file, [cscPage(3, 'Text page.')], ChunkEnhancement::Text, $previous));

    $prompts = Http::recorded()->map(fn (array $pair): string => json_encode($pair[0]->data()));

    expect($prompts)->toHaveCount(2)
        ->and($prompts[0])->toContain('Neighbour page about tides.')
        ->and($prompts[1])->not->toContain('Neighbour page about tides.');
});

it('does not enhance a page the source already holds', function (): void {
    cscFakeChunker();
    $agent = Agent::factory()->create();
    $file = AgentFileFactory::new()->create(['workspace_id' => $agent->workspace_id]);
    app(CreateSourceChunksAction::class)->execute(cscData($file, [cscPage(1, 'Stored plain.')]));

    app(CreateSourceChunksAction::class)->execute(cscData($file, [cscPage(1, 'Stored plain.')], ChunkEnhancement::Text));

    Http::assertNothingSent();
});

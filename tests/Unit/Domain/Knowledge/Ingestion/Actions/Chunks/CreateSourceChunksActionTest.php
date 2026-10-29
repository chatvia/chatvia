<?php

use Database\Factories\AgentFileFactory;
use Domain\Agent\Models\Agent;
use Domain\Knowledge\Embedding\Contracts\EmbeddingsProvider;
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
use Illuminate\Support\Str;
use Tests\Support\Knowledge\Embedding\FakeEmbeddingsProvider;

/**
 * Stores pages of a source as chunks: one embedding request and one insert
 * per group, pages the source already holds are skipped before anything is
 * paid for, and a page held plain while enhancement is on is enhanced on its
 * own row.
 */
function cscPage(int $number, string $content): PageData
{
    return new PageData(pageNumber: $number, content: $content, checksum: md5($content));
}

/**
 * @param  list<PageData>  $pages
 */
function cscData(SourceFile $file, array $pages, ChunkEnhancement $enhancement = ChunkEnhancement::Off): ChunkPagesData
{
    return new ChunkPagesData(
        source: $file,
        group: new PageGroupData($pages),
        enhancement: $enhancement,
    );
}

/** Keeps the texts sent to the embeddings provider from now on. */
function cscCountingEmbeddings(): FakeEmbeddingsProvider
{
    $provider = new FakeEmbeddingsProvider;

    app()->instance(EmbeddingsProvider::class, $provider);

    return $provider;
}

it('stores the new pages of a group with one embedding request and one insert, skipping pages the source holds', function (): void {
    $agent = Agent::factory()->create();
    $file = AgentFileFactory::new()->create(['workspace_id' => $agent->workspace_id]);
    app(CreateSourceChunksAction::class)->execute(cscData($file, [cscPage(1, 'Already stored.')]));
    $embeddings = cscCountingEmbeddings();
    $inserts = 0;
    DB::listen(function (QueryExecuted $query) use (&$inserts): void {
        $inserts += Str::startsWith($query->sql, 'insert into "chunks"') ? 1 : 0;
    });

    $created = app(CreateSourceChunksAction::class)->execute(cscData($file, [
        cscPage(1, 'Already stored.'),
        cscPage(3, 'The mitochondria is the powerhouse of the cell.'),
        cscPage(4, 'Chloroplasts perform photosynthesis in plant cells.'),
        cscPage(5, 'Chloroplasts perform photosynthesis in plant cells.'),
    ]));

    $chunk = Chunk::query()->findOrFail($created[md5('The mitochondria is the powerhouse of the cell.')]);

    expect($created->keys()->all())->toBe([md5('The mitochondria is the powerhouse of the cell.'), md5('Chloroplasts perform photosynthesis in plant cells.')])
        ->and($inserts)->toBe(1)
        ->and($embeddings->texts)->toBe(['The mitochondria is the powerhouse of the cell.', 'Chloroplasts perform photosynthesis in plant cells.'])
        ->and($file->chunks()->count())->toBe(3)
        ->and($chunk->chunk)->toBe('The mitochondria is the powerhouse of the cell.')
        ->and($chunk->chunk_index)->toBe(3)
        ->and($chunk->workspace_id)->toBe($agent->workspace_id)
        ->and($chunk->chunkable_type)->toBe((new SourceFile)->getMorphClass())
        ->and($chunk->chunkable_id)->toBe($file->id)
        ->and($chunk->is_enhanced)->toBeFalse()
        ->and($chunk->external_id)->not->toBeNull()
        ->and($chunk->tokens)->toBeGreaterThan(0);
});

it('enhances a page the source holds plain, on its own row', function (): void {
    Http::fake(fn (Request $request) => Http::response(['choices' => [['message' => ['content' => json_encode([
        'summary' => 'A page about oceans.',
        'questions' => ['Which ocean is largest?', 'How deep is it?'],
    ])]]], 'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 20]]));
    $agent = Agent::factory()->create();
    $file = AgentFileFactory::new()->create(['workspace_id' => $agent->workspace_id]);
    app(CreateSourceChunksAction::class)->execute(cscData($file, [cscPage(1, 'The Pacific is the largest ocean.')]));
    $plain = $file->chunks()->sole();
    $embeddings = cscCountingEmbeddings();

    $created = app(CreateSourceChunksAction::class)->execute(cscData($file, [cscPage(1, 'The Pacific is the largest ocean.')], ChunkEnhancement::Text));

    $chunk = $file->chunks()->sole();

    expect($created)->toBeEmpty()
        ->and($chunk->id)->toBe($plain->id)
        ->and($chunk->external_id)->toBe($plain->external_id)
        ->and($chunk->is_enhanced)->toBeTrue()
        ->and($chunk->summary)->toBe('A page about oceans.')
        ->and($chunk->questions)->toBe("Which ocean is largest?\nHow deep is it?")
        ->and($chunk->questions_embedding)->not->toBeNull()
        ->and($embeddings->texts)->toBe([
            "A page about oceans.\n\nThe Pacific is the largest ocean.",
            "Which ocean is largest?\nHow deep is it?",
        ]);

    Http::assertSentCount(1);
});

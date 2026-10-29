<?php

use Database\Factories\AgentFileFactory;
use Domain\Knowledge\Embedding\Actions\Reindex\ReembedChunksAction;
use Domain\Knowledge\Embedding\Contracts\EmbeddingsProvider;
use Domain\Knowledge\Embedding\Services\EmbeddingStorage;
use Domain\Knowledge\Ingestion\Models\Chunk;
use Domain\Knowledge\Source\File\Models\SourceFile;
use Illuminate\Support\Facades\DB;
use Tests\Support\Knowledge\Embedding\FakeEmbeddingsProvider;

/**
 * A re-index embeds the stored chunks of an id range again with the model in
 * use: their stored search_text, and their questions. Chunks outside the
 * range keep their vectors, and the search text stays as it is.
 */

/**
 * A model that gives every text the same vector (0.5 everywhere: exact in
 * half precision) and remembers what it embedded.
 */
function rcaNewModel(): FakeEmbeddingsProvider
{
    $provider = FakeEmbeddingsProvider::constant(0.5, 'new-model');

    app()->instance(EmbeddingsProvider::class, $provider);

    return $provider;
}

function rcaChunk(SourceFile $file, string $text, ?string $questions = null, ?string $summary = null): Chunk
{
    return Chunk::factory()->forSource($file)->create([
        'chunk' => $text,
        'summary' => $summary,
        'search_text' => $summary === null ? $text : "{$summary}\n\n{$text}",
        'questions' => $questions,
        'questions_embedding' => $questions === null ? null : embeddingVector(0.25),
        'embedding' => embeddingVector(0.25),
    ]);
}

/** Whether the chunk's vector column now holds the new model's vector. */
function rcaHasNewVector(string $column, int $id): bool
{
    $type = app(EmbeddingStorage::class)->columnType();

    return (bool) DB::scalar("SELECT {$column} = ?::{$type} FROM chunks WHERE id = ?", [embeddingVector(0.5), $id]);
}

it('embeds the chunks of a range again from their text and questions', function (): void {
    $file = AgentFileFactory::new()->create();
    $asked = rcaChunk($file, 'Opening hours.', questions: 'When are you open?', summary: 'About hours.');
    $plain = rcaChunk($file, 'Prices.');
    $outside = rcaChunk($file, 'Outside the range.');
    $keywords = Chunk::query()->whereKey($asked->id)->value('search_text');
    $provider = rcaNewModel();

    $done = app(ReembedChunksAction::class)->execute($asked->id, $plain->id);

    expect($done)->toBe(2)
        ->and($provider->texts)->toBe(["About hours.\n\nOpening hours.", 'Prices.', 'When are you open?'])
        ->and(rcaHasNewVector('embedding', $asked->id))->toBeTrue()
        ->and(rcaHasNewVector('questions_embedding', $asked->id))->toBeTrue()
        ->and(rcaHasNewVector('embedding', $plain->id))->toBeTrue()
        ->and(Chunk::query()->whereKey($plain->id)->value('questions_embedding'))->toBeNull()
        ->and(rcaHasNewVector('embedding', $outside->id))->toBeFalse()
        ->and(Chunk::query()->whereKey($asked->id)->value('search_text'))->toBe($keywords);
});

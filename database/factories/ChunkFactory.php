<?php

declare(strict_types=1);

namespace Database\Factories;

use Domain\Knowledge\Ingestion\Models\Chunk;
use Domain\Knowledge\Ingestion\Models\ChunkReference;
use Domain\Knowledge\Source\File\Models\SourceFile;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;

/**
 * Builds a page chunk (`chunk_index` 1) of a knowledge source, in the source's
 * workspace. Without {@see forSource()} it belongs to a new file. The dense
 * vector is the same everywhere (0.1), so use {@see withVector()} when the
 * order of the results matters; the lexical search text is the chunk text,
 * as ingestion writes it.
 *
 * @extends Factory<Chunk>
 */
class ChunkFactory extends Factory
{
    #[\Override]
    protected $model = Chunk::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'chunkable_type' => (new SourceFile)->getMorphClass(),
            'chunkable_id' => AgentFileFactory::new(),
            'workspace_id' => fn (array $attributes): int => $this->sourceOf($attributes)->getAttribute('workspace_id'),
            'chunk_index' => 1,
            'chunk' => fake()->sentence(),
            'tokens' => 3,
            'embedding' => $this->vectorLiteral(array_fill(0, (int) config('chatvia.embeddings.dimensions'), 0.1)),
            'search_text' => fn (array $attributes): string => (string) $attributes['chunk'],
            // Unique per source, so two chunks with the same text never collide.
            'checksum' => fn (): string => hash('sha256', (string) Str::uuid7()),
        ];
    }

    /** A chunk of the given source, in the source's workspace. */
    public function forSource(Model $source): static
    {
        return $this->state(fn (): array => [
            'workspace_id' => $source->getAttribute('workspace_id'),
            'chunkable_type' => $source->getMorphClass(),
            'chunkable_id' => $source->getKey(),
        ]);
    }

    /**
     * A website chunk that these pages produced: one `chunk_references` row
     * per page, under the page's morph alias.
     */
    public function referencedBy(Model ...$pages): static
    {
        return $this->afterCreating(function (Chunk $chunk) use ($pages): void {
            ChunkReference::query()->insert(array_map(fn (Model $page): array => [
                'chunk_id' => $chunk->id,
                'referenceable_type' => $page->getMorphClass(),
                'referenceable_id' => $page->getKey(),
            ], array_values($pages)));
        });
    }

    /** The source's summary chunk (`chunk_index` 0). */
    public function summary(): static
    {
        return $this->state(['chunk_index' => 0]);
    }

    /**
     * A chunk with this dense vector.
     *
     * @param  list<float>  $vector
     */
    public function withVector(array $vector): static
    {
        return $this->state(['embedding' => $this->vectorLiteral($vector)]);
    }

    /** @param  list<float>  $vector */
    private function vectorLiteral(array $vector): string
    {
        return '['.implode(',', $vector).']';
    }

    /** @param  array<string, mixed>  $attributes */
    private function sourceOf(array $attributes): Model
    {
        /** @var class-string<Model> $class */
        $class = Relation::getMorphedModel($attributes['chunkable_type']) ?? $attributes['chunkable_type'];

        return $class::query()->findOrFail($attributes['chunkable_id']);
    }
}

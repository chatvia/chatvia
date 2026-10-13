<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Identity\Workspace\Models\User;
use Domain\Knowledge\Source\File\Models\SourceFile;
use Domain\Knowledge\Source\Services\KnowledgeStoragePath;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Builds a knowledge-base file (a {@see SourceFile} row) with its own owner.
 * Only the row is made; no file is written to storage, but `filepath` points
 * into the source's own folder ({@see KnowledgeStoragePath}), as a real upload
 * does. Use `AgentFileFactory::new()`, because `SourceFile` does not resolve
 * this factory by name.
 *
 * @extends Factory<SourceFile>
 */
class AgentFileFactory extends Factory
{
    #[\Override]
    protected $model = SourceFile::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // Each file gets its own workspace so tests never depend on seeded users.
            'workspace_id' => fn (): int => User::factory()->create()->workspace()->id,
            'filename' => fake()->word().'.txt',
            'filetype' => 'pdf',
            'external_id' => fn (): string => (string) Str::uuid7(),
            'filepath' => fn (array $attributes): string => KnowledgeStoragePath::forSource(new SourceFile([
                'workspace_id' => $attributes['workspace_id'],
                'external_id' => $attributes['external_id'],
            ])).'/'.fake()->uuid().'.txt',
            'size_in_kb' => fake()->numberBetween(1, 1000),
            'mime_type' => 'text/plain',
            'checksum' => fake()->md5(),
            'status' => 'uploaded',
            'length' => fake()->numberBetween(1, 50),
        ];
    }
}

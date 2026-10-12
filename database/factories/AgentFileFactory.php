<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Identity\Workspace\Models\User;
use Domain\Knowledge\Source\File\Models\SourceFile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Builds a knowledge-base file (a {@see SourceFile} row) with its own owner.
 * Only the row is made; no file is written to storage. Use
 * `AgentFileFactory::new()`, because `SourceFile` does not resolve this factory
 * by name.
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
            'filepath' => 'test/files/'.fake()->uuid().'.txt',
            'size_in_kb' => fake()->numberBetween(1, 1000),
            'mime_type' => 'text/plain',
            'checksum' => fake()->md5(),
            'status' => 'uploaded',
            'length' => fake()->numberBetween(1, 50),
        ];
    }
}

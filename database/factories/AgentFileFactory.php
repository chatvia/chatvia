<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Identity\Workspace\Models\User;
use Domain\Knowledge\Source\File\Models\SourceFile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
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
            // Each file gets its own owner so tests never depend on seeded users.
            'user_id' => User::factory(),
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

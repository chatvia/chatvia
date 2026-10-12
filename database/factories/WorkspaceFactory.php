<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Identity\Workspace\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * A bare workspace for tests. Most tests get one through UserFactory, which
 * creates the workspace together with its owner.
 *
 * @extends Factory<Workspace>
 */
class WorkspaceFactory extends Factory
{
    #[\Override]
    protected $model = Workspace::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'external_id' => (string) Str::uuid7(),
            'name' => fake()->company(),
        ];
    }
}

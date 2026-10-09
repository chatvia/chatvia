<?php

declare(strict_types=1);

namespace Database\Factories;

use Domain\Integration\Mcp\Enums\McpToolPermission;
use Domain\Integration\Mcp\Enums\McpToolScope;
use Domain\Integration\Mcp\Models\ConnectorTool;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Builds a row of the shared connector tool catalog: a write tool that needs
 * approval by default and has no embeddings yet.
 *
 * @extends Factory<ConnectorTool>
 */
class ConnectorToolFactory extends Factory
{
    #[\Override]
    protected $model = ConnectorTool::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'connector_slug' => fake()->unique()->slug(2),
            'name' => fake()->unique()->word(),
            'description' => fake()->sentence(),
            'input_schema' => ['type' => 'object', 'properties' => []],
            'annotations' => null,
            'scope' => McpToolScope::Write,
            'default_permission' => McpToolPermission::Approval,
            'embedding' => null,
            'embedding_bm25' => null,
            'fetched_at' => now(),
        ];
    }
}

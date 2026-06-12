<?php

namespace Database\Factories;

use Domain\Integration\Mcp\Enums\McpToolPermission;
use Domain\Integration\Mcp\Enums\McpToolScope;
use Domain\Integration\Mcp\Models\ConnectorTool;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ConnectorTool>
 */
class ConnectorToolFactory extends Factory
{
    protected $model = ConnectorTool::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'connector_slug' => $this->faker->unique()->slug(2),
            'name' => $this->faker->unique()->word(),
            'description' => $this->faker->sentence(),
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

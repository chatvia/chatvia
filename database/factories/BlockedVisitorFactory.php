<?php

namespace Database\Factories;

use App\Identity\Workspace\Models\Workspace;
use Domain\Security\Models\BlockedVisitor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Builds a visitor block that lasts a week, in a fresh workspace.
 *
 * @extends Factory<BlockedVisitor>
 */
class BlockedVisitorFactory extends Factory
{
    #[\Override]
    protected $model = BlockedVisitor::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'visitor_key' => hash('sha256', fake()->uuid()),
            'browser' => 'Chrome',
            'os' => 'macOS',
            'expires_at' => now()->addWeek(),
        ];
    }

    /** A block whose end has passed. */
    public function expired(): static
    {
        return $this->state(fn (array $attributes): array => ['expires_at' => now()->subMinute()]);
    }
}

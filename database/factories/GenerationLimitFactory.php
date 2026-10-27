<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Identity\Workspace\Models\GenerationLimit;
use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A monthly cap of 50 pictures and audio files for a new workspace, no video
 * cap, nothing made yet this month.
 *
 * @extends Factory<GenerationLimit>
 */
class GenerationLimitFactory extends Factory
{
    #[\Override]
    protected $model = GenerationLimit::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => fn (): int => User::factory()->create()->workspace()->id,
            'monthly_limit' => 50,
            'generations_used' => 0,
            'monthly_video_limit' => null,
            'videos_used' => 0,
            'period_starts_at' => fn () => now()->startOfMonth(),
        ];
    }

    public function forWorkspace(Workspace $workspace): static
    {
        return $this->state(['workspace_id' => $workspace->id]);
    }

    /** Every file of this month's cap is made. */
    public function reached(): static
    {
        return $this->state(fn (array $attributes): array => ['generations_used' => $attributes['monthly_limit']]);
    }

    /** Counted last month, so this month starts from zero. */
    public function lastMonth(): static
    {
        return $this->state(fn (): array => ['period_starts_at' => now()->subMonthNoOverflow()->startOfMonth()]);
    }
}

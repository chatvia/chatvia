<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Models\MessageLimit;
use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A monthly message limit of 100 for a new workspace, nothing used yet this
 * month. {@see forChatter()} puts the limit on one chatter instead; a limit is
 * on a workspace or on a chatter, never both.
 *
 * @extends Factory<MessageLimit>
 */
class MessageLimitFactory extends Factory
{
    #[\Override]
    protected $model = MessageLimit::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => fn (): int => User::factory()->create()->workspace()->id,
            'chatter_id' => null,
            'monthly_limit' => 100,
            'messages_used' => 0,
            'period_starts_at' => fn () => now()->startOfMonth(),
        ];
    }

    public function forWorkspace(Workspace $workspace): static
    {
        return $this->state(['workspace_id' => $workspace->id, 'chatter_id' => null]);
    }

    public function forChatter(Chatter $chatter): static
    {
        return $this->state(['workspace_id' => null, 'chatter_id' => $chatter->id]);
    }

    /** Every message of this month's limit is used up. */
    public function reached(): static
    {
        return $this->state(fn (array $attributes): array => ['messages_used' => $attributes['monthly_limit']]);
    }

    /** Counted last month, so this month starts from zero. */
    public function lastMonth(): static
    {
        return $this->state(fn (): array => ['period_starts_at' => now()->subMonthNoOverflow()->startOfMonth()]);
    }
}

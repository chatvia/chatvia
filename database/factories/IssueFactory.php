<?php

namespace Database\Factories;

use App\Admin\Issue\Enums\IssueStatus;
use App\Admin\Issue\Models\Issue;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Support\Radar\IssueTypeKey;

/**
 * Builds an open "fal.ai refused the API key" issue seen once, just now.
 *
 * @extends Factory<Issue>
 */
class IssueFactory extends Factory
{
    #[\Override]
    protected $model = Issue::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'fingerprint' => Str::of(fake()->unique()->uuid())->hash('sha1')->toString(),
            'service' => 'fal',
            'kind' => IssueTypeKey::KEY_REFUSED,
            'status' => IssueStatus::Open,
            'message' => 'Invalid API key',
            'occurrences_count' => 1,
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ];
    }

    /** Last seen $days days ago. */
    public function seenDaysAgo(int $days): static
    {
        return $this->state(fn (): array => ['first_seen_at' => now()->subDays($days), 'last_seen_at' => now()->subDays($days)]);
    }
}

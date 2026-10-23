<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Knowledge\Source\Enums\SourceStatus;
use Domain\Knowledge\Source\Youtube\Models\SourceYoutubeVideo;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * A YouTube video added to the knowledge base, waiting to be trained.
 *
 * @extends Factory<SourceYoutubeVideo>
 */
class SourceYoutubeVideoFactory extends Factory
{
    #[\Override]
    protected $model = SourceYoutubeVideo::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // Each source gets its own workspace unless attachedTo() names an agent.
            'workspace_id' => fn (): int => User::factory()->create()->workspace()->id,
            'external_id' => fn (): string => (string) Str::uuid7(),
            'url' => fn (): string => 'https://www.youtube.com/watch?v='.Str::random(11),
            'title' => 'Getting started',
            'length' => 1,
            'status' => SourceStatus::Pending,
        ];
    }

    /** In the agent's workspace and linked to the agent's knowledge. */
    public function attachedTo(Agent $agent): static
    {
        return $this->state(['workspace_id' => $agent->workspace_id])
            ->afterCreating(fn (SourceYoutubeVideo $source) => $agent->youtubeVideos()->attach($source->getKey()));
    }

    /** Trained: its chunks are in the knowledge base. */
    public function ingested(): static
    {
        return $this->state(['status' => SourceStatus::Completed]);
    }

    public function processing(): static
    {
        return $this->state(['status' => SourceStatus::Processing]);
    }
}

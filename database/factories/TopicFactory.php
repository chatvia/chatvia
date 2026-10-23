<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Topic\Models\Topic;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * A workspace topic of a new workspace, with a name of its own (a name is
 * unique in its workspace). {@see attachedTo()} puts it in an agent's
 * workspace and gives it to the agent.
 *
 * @extends Factory<Topic>
 */
class TopicFactory extends Factory
{
    #[\Override]
    protected $model = Topic::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => fn (): int => User::factory()->create()->workspace()->id,
            'topic' => fn (): string => 'Topic '.Str::random(8),
            'description' => 'What visitors ask about this',
        ];
    }

    public function attachedTo(Agent $agent): static
    {
        return $this->state(['workspace_id' => $agent->workspace_id])
            ->afterCreating(fn (Topic $topic) => $agent->topics()->attach($topic->id));
    }
}

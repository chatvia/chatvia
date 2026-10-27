<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Knowledge\Source\Enums\SourceStatus;
use Domain\Knowledge\Source\Services\KnowledgeStoragePath;
use Domain\Knowledge\Source\Text\Models\SourceText;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * A pasted knowledge text, waiting to be trained. Only the row is made: no file
 * is written, but `filepath` points into the source's own folder
 * ({@see KnowledgeStoragePath}), as a real text does.
 *
 * @extends Factory<SourceText>
 */
class SourceTextFactory extends Factory
{
    #[\Override]
    protected $model = SourceText::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // Each source gets its own workspace unless attachedTo() names an agent.
            'workspace_id' => fn (): int => User::factory()->create()->workspace()->id,
            'external_id' => fn (): string => (string) Str::uuid7(),
            'filepath' => fn (array $attributes): string => KnowledgeStoragePath::forSource(new SourceText([
                'workspace_id' => $attributes['workspace_id'],
                'external_id' => $attributes['external_id'],
            ])).'/source.txt',
            'excerpt' => 'Our refund policy',
            'length' => 1,
            'checksum' => fn (): string => md5(Str::uuid7()->toString()),
            'status' => SourceStatus::Pending,
        ];
    }

    /** In the agent's workspace and linked to the agent's knowledge. */
    public function attachedTo(Agent $agent): static
    {
        return $this->state(['workspace_id' => $agent->workspace_id])
            ->afterCreating(fn (SourceText $source) => $agent->texts()->attach($source->getKey()));
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

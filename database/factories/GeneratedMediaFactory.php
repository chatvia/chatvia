<?php

declare(strict_types=1);

namespace Database\Factories;

use Domain\Agent\Models\Agent;
use Domain\Conversation\Enums\GeneratedMediaFailure;
use Domain\Conversation\Enums\GeneratedMediaKind;
use Domain\Conversation\Enums\GeneratedMediaStatus;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\GeneratedMedia;
use Domain\Conversation\Services\Media\GeneratedMediaFiles;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * A square image an agent was asked for in a conversation, still waiting
 * for its job. {@see ready()} gives it a file path, {@see stored()} also
 * writes the file (fake the disk first).
 *
 * @extends Factory<GeneratedMedia>
 */
class GeneratedMediaFactory extends Factory
{
    #[\Override]
    protected $model = GeneratedMedia::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'chat_conversation_id' => Conversation::factory(),
            'workspace_id' => fn (array $attributes): int => (int) Conversation::query()->whereKey($attributes['chat_conversation_id'])->value('workspace_id'),
            'agent_id' => fn (array $attributes): int => (int) Conversation::query()->whereKey($attributes['chat_conversation_id'])->value('agent_id'),
            'kind' => GeneratedMediaKind::Image,
            'status' => GeneratedMediaStatus::Pending,
            'prompt' => 'A red bicycle by the sea',
            'metadata' => ['shape' => 'square', 'model' => 'fal-ai/flux-2'],
            'service' => 'fal',
        ];
    }

    public function forConversation(Conversation $conversation): static
    {
        return $this->state([
            'chat_conversation_id' => $conversation->id,
            'workspace_id' => $conversation->workspace_id,
            'agent_id' => $conversation->agent_id,
        ]);
    }

    public function audio(): static
    {
        return $this->state([
            'kind' => GeneratedMediaKind::Audio,
            'prompt' => 'Welcome to our shop.',
            'metadata' => ['voice' => 'fake-voice'],
            'service' => 'fake:speech-model',
        ]);
    }

    /** A landscape video, started at its service (`task_id`) and still being made. */
    public function video(): static
    {
        return $this->state([
            'kind' => GeneratedMediaKind::Video,
            'prompt' => 'Waves rolling onto a beach at sunset',
            'metadata' => ['shape' => 'landscape', 'seconds' => 8, 'resolution' => '720p', 'model' => 'fal-ai/wan/v2.2-a14b/text-to-video'],
            'service' => 'fal',
            'task_id' => 'fake-task-1',
        ]);
    }

    /** Made: its file path is set under the agent's folder. */
    public function ready(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => GeneratedMediaStatus::Ready,
            'mime_type' => match ($attributes['kind'] ?? null) {
                GeneratedMediaKind::Audio => 'audio/mpeg',
                GeneratedMediaKind::Video => 'video/mp4',
                default => 'image/png',
            },
            'size_bytes' => 4,
        ])->afterMaking(function (GeneratedMedia $media): void {
            $conversation = Conversation::query()->find($media->chat_conversation_id);
            $agent = Agent::withTrashed()->find($media->agent_id);

            if ($conversation instanceof Conversation && $agent instanceof Agent && $media->filepath === null) {
                $media->external_id ??= (string) Str::uuid7();
                $media->filepath = app(GeneratedMediaFiles::class)->pathFor(
                    $agent,
                    $conversation->external_id,
                    $media,
                    $media->kind->extension(),
                );
            }
        });
    }

    public function failed(GeneratedMediaFailure $failure = GeneratedMediaFailure::Failed): static
    {
        return $this->state(['status' => GeneratedMediaStatus::Failed, 'failure' => $failure]);
    }

    /** Ready, with its file written to the private disk. */
    public function stored(string $contents = 'file'): static
    {
        return $this->ready()->afterCreating(fn (GeneratedMedia $media) => Storage::disk(GeneratedMedia::DISK)->put((string) $media->filepath, $contents));
    }
}

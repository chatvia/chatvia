<?php

namespace Domain\Llm\Operations\Starters;

use Domain\Llm\Data\Chat\ChatMessageData;
use Domain\Llm\Data\Chat\ChatRequestData;
use Domain\Llm\Enums\AiCallTag;
use Domain\Llm\Enums\LlmTask;
use Domain\Llm\Services\StructuredCompletion;
use Domain\Llm\Services\TaskModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Suggests conversation starters from a sample of an agent's knowledge-base
 * content. One LLM call returns up to N short questions a visitor might ask,
 * grounded in the supplied content. Returns plain strings — the Setup UI lets
 * the owner pick which to add (icons stay the owner's choice).
 */
class StarterGenerator
{
    /** The starters endpoint caps each entry at 255 characters. */
    private const int MAX_LENGTH = 255;

    public function __construct(
        private readonly StructuredCompletion $completion,
        private readonly TaskModels $models,
    ) {}

    /**
     * @param  array<int, string>  $content  knowledge-base content samples
     * @return array<int, string> suggested starter questions (≤ $count)
     */
    public function generate(array $content, int $count): array
    {
        $count = max(1, min(10, $count));

        if ($content === []) {
            return [];
        }

        $reply = $this->completion->send(new ChatRequestData(
            model: $this->models->model(LlmTask::Starters),
            messages: [
                ChatMessageData::system(prompt('starters/generate', ['count' => $count])),
                ChatMessageData::user(prompt('starters/content', [
                    'content' => Collection::make($content)->take(40)->implode("\n---\n"),
                ])),
            ],
            tag: AiCallTag::ConversationStarters,
            temperature: 0.7,
        ));

        return Collection::make($reply->data ?? [])
            ->filter(fn (mixed $value): bool => filled($value))
            ->map(fn (string $value): string => Str::trim($value))
            ->filter(fn (string $value): bool => $value !== '' && Str::length($value) <= self::MAX_LENGTH)
            ->unique()
            ->take($count)
            ->values()
            ->all();
    }
}

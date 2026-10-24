<?php

namespace Domain\Knowledge\Source\Text\Data;

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Source\Text\Models\SourceText;
use Illuminate\Support\Str;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One row on the "available trained texts" list for an agent — every
 * ingested text in the account that isn't already linked to this agent.
 */
#[TypeScript]
class TrainedTextRowData extends Data
{
    public function __construct(
        public int $id,
        public string $excerpt,
        public int $word_count,
        /** @var array<int, array<string, mixed>> */
        public array $agents,
    ) {}

    public static function fromModel(SourceText $text, int $wordCount): self
    {
        return new self(
            id: $text->id,
            // Character-based cut so multibyte text (Arabic, CJK, emoji) never
            // splits in the middle of a character.
            excerpt: Str::substr((string) $text->excerpt, 0, 200),
            word_count: $wordCount,
            agents: $text->agents
                ->map(fn (Agent $agent): array => ['id' => $agent->id, 'name' => $agent->name])
                ->all(),
        );
    }
}

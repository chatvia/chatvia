<?php

namespace Domain\Source\Text\Data;

use Domain\Agent\Models\Agent;
use Domain\Source\Text\Models\SourceText;
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

    public static function fromModel(SourceText $text): self
    {
        return new self(
            id: $text->id,
            excerpt: substr($text->content, 0, 200),
            word_count: str_word_count($text->content),
            agents: $text->agents
                ->map(fn (Agent $agent): array => ['id' => $agent->id, 'name' => $agent->name])
                ->all(),
        );
    }
}

<?php

declare(strict_types=1);

namespace Domain\Agent\Data\Resources\ChatLog\Messages;

use Domain\Conversation\Models\MessageSource;
use Domain\Knowledge\Retrieval\Enums\SourceKind;
use Illuminate\Support\Str;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One piece of knowledge an answer in the chat log was given, best first:
 * its source, how relevant the search found it and the start of its text,
 * so staff can tell whether a bad answer came from the search or from the
 * model. The fields of the chat clients' source list come first, so the same
 * sources dialog shows both.
 */
#[TypeScript]
class ChatLogMessageSourceData extends Data
{
    /** How much of a chunk's text the preview keeps. */
    private const int PREVIEW_LENGTH = 400;

    public function __construct(
        public ?string $id,
        public string $label,
        public SourceKind $kind,
        public ?string $url,
        public ?int $page,
        public int $passages,
        public int $rank,
        public ?float $score,
        // Null when the chunk is gone (its source was retrained or deleted).
        public ?string $preview,
    ) {}

    public static function fromSource(MessageSource $source): self
    {
        return new self(
            id: null,
            label: $source->label,
            kind: $source->kind(),
            url: $source->url,
            page: $source->page,
            passages: 1,
            rank: $source->rank,
            score: $source->score,
            preview: $source->chunk !== null
                ? Str::of($source->chunk->chunk)->squish()->limit(self::PREVIEW_LENGTH, preserveWords: true)->value()
                : null,
        );
    }
}

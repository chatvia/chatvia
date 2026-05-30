<?php

namespace Domain\Agents\Data\Resources\ChatLog;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One visitor message's tone/sentiment snapshot inside a conversation pulse.
 */
#[TypeScript]
class ChatLogPulseMessageData extends Data
{
    public function __construct(
        public int $index,
        public string $content_preview,
        public ?string $tone,
        public ?string $sentiment,
        public float $sentiment_score,
        public float $tone_score,
        public string $created_at,
    ) {}
}

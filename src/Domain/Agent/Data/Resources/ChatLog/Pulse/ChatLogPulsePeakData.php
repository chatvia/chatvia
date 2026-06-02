<?php

namespace Domain\Agent\Data\Resources\ChatLog\Pulse;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The single high-water or low-water mark within a conversation's pulse
 * (peak negative or peak positive moment).
 */
#[TypeScript]
class ChatLogPulsePeakData extends Data
{
    public function __construct(
        public int $index,
        public ?string $tone,
        public ?string $sentiment,
    ) {}
}

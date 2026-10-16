<?php

declare(strict_types=1);

namespace Domain\Agent\Data\Resources\ChatLog\Pulse;

use Domain\Analytics\Enums\Sentiment;
use Domain\Analytics\Enums\Tone;
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
        public ?Tone $tone,
        public ?Sentiment $sentiment,
    ) {}
}

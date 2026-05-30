<?php

namespace Domain\Agents\Data\Resources\ChatLog;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Headline summary of a conversation's pulse — captured for the chat-log
 * card so it can render without re-running the calculation on the client.
 */
#[TypeScript]
class ChatLogPulseSummaryData extends Data
{
    public function __construct(
        public ?ChatLogPulseSnapshotData $started_as,
        public ?ChatLogPulseSnapshotData $ended_as,
        public ?ChatLogPulsePeakData $peak_negative,
        public ?ChatLogPulsePeakData $peak_positive,
        public float $avg_sentiment,
        public string $trend,
        public bool $resolution,
    ) {}
}

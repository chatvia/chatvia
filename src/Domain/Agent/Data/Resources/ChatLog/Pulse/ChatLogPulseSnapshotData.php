<?php

declare(strict_types=1);

namespace Domain\Agent\Data\Resources\ChatLog\Pulse;

use Domain\Analytics\Enums\Sentiment;
use Domain\Analytics\Enums\Tone;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Per-end snapshot of a conversation's mood — used to describe both the
 * start ("started_as") and the end ("ended_as") of a pulse.
 */
#[TypeScript]
class ChatLogPulseSnapshotData extends Data
{
    public function __construct(
        public ?Tone $tone,
        public ?Sentiment $sentiment,
    ) {}
}

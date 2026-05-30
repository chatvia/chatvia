<?php

namespace Domain\Agents\Data\Resources\ChatLog;

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
        public ?string $tone,
        public ?string $sentiment,
    ) {}
}

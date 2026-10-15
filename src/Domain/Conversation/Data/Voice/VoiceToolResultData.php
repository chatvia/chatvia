<?php

declare(strict_types=1);

namespace Domain\Conversation\Data\Voice;

use Domain\Conversation\Enums\VoiceToolOutcome;
use Spatie\LaravelData\Data;

/**
 * The answer to a tool call in a voice call: the text the model reads, and
 * how the call ended, which the call view shows on the tool's chip.
 */
class VoiceToolResultData extends Data
{
    public function __construct(
        public string $output,
        public VoiceToolOutcome $outcome,
    ) {}
}

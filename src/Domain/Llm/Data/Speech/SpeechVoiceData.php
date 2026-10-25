<?php

declare(strict_types=1);

namespace Domain\Llm\Data\Speech;

use Spatie\LaravelData\Data;

/**
 * A voice a text-to-speech service reads with: the id it is called by, and
 * the name shown in the voice pickers.
 */
class SpeechVoiceData extends Data
{
    public function __construct(
        public string $id,
        public string $label,
    ) {}
}

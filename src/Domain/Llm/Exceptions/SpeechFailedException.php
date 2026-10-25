<?php

declare(strict_types=1);

namespace Domain\Llm\Exceptions;

use RuntimeException;

/**
 * A text-to-speech service could not read a text: it failed, timed out, or
 * (OpenAI's realtime model) said other words than the text, twice.
 */
class SpeechFailedException extends RuntimeException {}

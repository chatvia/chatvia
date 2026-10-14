<?php

declare(strict_types=1);

namespace Domain\Conversation\Exceptions;

use Domain\Conversation\Enums\VoiceCallRefusal;
use RuntimeException;
use Throwable;

/**
 * A voice call was not opened, for the {@see VoiceCallRefusal} it carries.
 * The voice controllers answer it as JSON with the refusal's code, status
 * and message.
 */
final class VoiceCallRefusedException extends RuntimeException
{
    public function __construct(
        public readonly VoiceCallRefusal $refusal,
        ?Throwable $previous = null,
    ) {
        parent::__construct($refusal->message(), previous: $previous);
    }
}

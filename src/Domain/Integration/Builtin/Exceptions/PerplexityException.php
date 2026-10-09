<?php

namespace Domain\Integration\Builtin\Exceptions;

use RuntimeException;

/**
 * A Perplexity call that produced no usable answer. The message is written
 * for the model to read.
 */
final class PerplexityException extends RuntimeException
{
    public static function failed(int $status): self
    {
        return new self("Perplexity returned HTTP {$status}.");
    }

    public static function noAnswer(): self
    {
        return new self('Perplexity returned no answer.');
    }
}

<?php

declare(strict_types=1);

namespace Domain\Llm\Exceptions;

use Domain\Llm\Models\Llm;
use RuntimeException;
use Throwable;

/**
 * A call to an AI model failed: its provider could not be reached, or it
 * refused the request. The message names the model and says what the
 * provider said, so the person who set it up knows what to fix.
 */
final class LlmCallFailedException extends RuntimeException
{
    public static function unreachable(Llm $model, Throwable $previous): self
    {
        return new self("{$model->provider->name} could not be reached to run the model \"{$model->name}\". Try again in a minute.", previous: $previous);
    }

    public static function refused(Llm $model, int $status, ?string $reason, Throwable $previous): self
    {
        $message = "The model \"{$model->name}\" on {$model->provider->name} did not answer (error {$status})";

        return new self($message.($reason !== null ? ": {$reason}" : '.'), previous: $previous);
    }
}

<?php

declare(strict_types=1);

namespace Domain\Llm\Exceptions;

use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * An image service could not make a picture: it failed, timed out, sent no
 * image, or (when {@see $refused} is true) refused the prompt under its
 * safety rules, so trying again with the same prompt will not help.
 */
class ImageGenerationFailedException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly bool $refused = false,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public static function refused(string $service, string $reason = ''): self
    {
        return new self(Str::rtrim("{$service} refused to make this image. {$reason}"), refused: true);
    }
}

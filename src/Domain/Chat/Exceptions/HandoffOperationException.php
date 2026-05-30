<?php

namespace Domain\Chat\Exceptions;

use RuntimeException;

/**
 * Thrown by handoff Actions when an HTTP-translatable failure happens
 * (forbidden, already-handled, send-failed, …). The controller catches it
 * and returns a JSON envelope with the embedded status code.
 */
class HandoffOperationException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $status,
    ) {
        parent::__construct($message);
    }

    public static function unauthorized(): self
    {
        return new self('Unauthorized.', 403);
    }

    public static function forbidden(string $message): self
    {
        return new self($message, 403);
    }

    public static function conflict(string $message): self
    {
        return new self($message, 409);
    }

    public static function failed(string $message, int $status = 500): self
    {
        return new self($message, $status);
    }
}

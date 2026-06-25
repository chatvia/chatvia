<?php

namespace Domain\Form\Exceptions;

use RuntimeException;

/**
 * Thrown when an inline form can't be submitted: it was already submitted
 * (409) or it has expired (410). Carries the HTTP status the controller maps to.
 */
class FormNotClaimableException extends RuntimeException
{
    public function __construct(public readonly int $status, string $message)
    {
        parent::__construct($message);
    }

    public static function alreadySubmitted(): self
    {
        return new self(409, 'This form has already been submitted.');
    }

    public static function expired(): self
    {
        return new self(410, 'This form has expired.');
    }
}

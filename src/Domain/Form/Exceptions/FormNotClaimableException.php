<?php

namespace Domain\Form\Exceptions;

use Domain\Form\Enums\FormClaimFailure;
use RuntimeException;

/**
 * Thrown when an inline form can't be submitted: it was already submitted or
 * it has expired ({@see FormClaimFailure}). The HTTP layer picks the response
 * status from the reason.
 */
class FormNotClaimableException extends RuntimeException
{
    public function __construct(public readonly FormClaimFailure $reason)
    {
        parent::__construct($reason->message());
    }

    public static function alreadySubmitted(): self
    {
        return new self(FormClaimFailure::AlreadySubmitted);
    }

    public static function expired(): self
    {
        return new self(FormClaimFailure::Expired);
    }
}

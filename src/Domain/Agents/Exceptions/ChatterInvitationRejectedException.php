<?php

namespace Domain\Agents\Exceptions;

use RuntimeException;

/**
 * Thrown when a signed agent invitation cannot be accepted — wrong email,
 * email already used by a non-chatter account, etc. Carries the form field
 * the controller should bind the error to.
 */
class ChatterInvitationRejectedException extends RuntimeException
{
    public function __construct(
        public readonly string $field,
        string $message,
    ) {
        parent::__construct($message);
    }
}

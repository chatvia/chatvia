<?php

declare(strict_types=1);

namespace Domain\Membership\Agent\Exceptions;

use RuntimeException;

/**
 * Thrown when a signed agent invitation cannot be accepted — wrong email,
 * email already used by a non-chatter account, etc. Carries the form field
 * the controller should bind the error to.
 */
class AgentChatterInvitationRejectedException extends RuntimeException
{
    public function __construct(
        public readonly string $field,
        string $message,
    ) {
        parent::__construct($message);
    }
}

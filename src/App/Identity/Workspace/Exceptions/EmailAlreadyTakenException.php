<?php

namespace App\Identity\Workspace\Exceptions;

use DomainException;

/**
 * An account with this email already exists.
 */
class EmailAlreadyTakenException extends DomainException
{
    public static function for(string $email): self
    {
        return new self("An account with the email {$email} already exists.");
    }
}

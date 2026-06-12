<?php

namespace Domain\Security\Exceptions;

use RuntimeException;

/**
 * Thrown when a configured SIEM webhook URL fails the SSRF guard — it must be
 * an https URL whose host does not resolve to a loopback / private / reserved
 * address. Surfaced to the user as a validation error by the controller.
 */
class UnsafeWebhookUrlException extends RuntimeException
{
    public static function for(string $reason): self
    {
        return new self($reason);
    }
}

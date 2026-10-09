<?php

namespace Domain\Form\Enums;

/**
 * Why an inline form could not be submitted. The values are the `status`
 * the chat clients read from the error response, so they must not change.
 */
enum FormClaimFailure: string
{
    case AlreadySubmitted = 'already_submitted';
    case Expired = 'expired';

    public function message(): string
    {
        return match ($this) {
            self::AlreadySubmitted => 'This form has already been submitted.',
            self::Expired => 'This form has expired.',
        };
    }
}

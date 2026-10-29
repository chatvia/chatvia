<?php

declare(strict_types=1);

namespace Domain\Conversation\Enums;

/**
 * What one check on a started video found, so the check's job knows
 * whether to put itself back on the queue for another look.
 */
enum VideoCheckResult
{
    // Still being made, or the service could not be reached this time.
    case Waiting;
    // Ready, failed, or nothing to check.
    case Settled;
}

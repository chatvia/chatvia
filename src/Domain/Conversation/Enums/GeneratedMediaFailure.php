<?php

declare(strict_types=1);

namespace Domain\Conversation\Enums;

/**
 * Why a generated file was not made, so the chat can say it plainly: the
 * service refused the request (its safety rules), the call failed, or it ran
 * out of time.
 */
enum GeneratedMediaFailure: string
{
    case Refused = 'refused';
    case Failed = 'failed';
    case TimedOut = 'timed_out';
}

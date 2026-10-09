<?php

declare(strict_types=1);

namespace Domain\Membership\Agent\Enums;

/**
 * Which credential the invited chatter is finalising their account with.
 */
enum InvitationCredentialKind: string
{
    case Password = 'password';
    case Social = 'social';
}

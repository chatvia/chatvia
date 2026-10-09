<?php

namespace App\Identity\Workspace\Enums;

/**
 * OAuth/social-login provider that a `user_socials` row links a user to.
 */
enum SocialProvider: string
{
    case Google = 'google';
    case Microsoft = 'microsoft';
    case Slack = 'slack';
    case Github = 'github';
    case LinkedIn = 'linkedin';
    case X = 'x';
}

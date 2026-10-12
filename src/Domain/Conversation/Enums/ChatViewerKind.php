<?php

declare(strict_types=1);

namespace Domain\Conversation\Enums;

/**
 * Which table a signed-in chat viewer comes from. Chatters and staff accounts
 * have separate id sequences, so an id alone never says who someone is.
 */
enum ChatViewerKind: string
{
    case Chatter = 'chatter';
    case Staff = 'user';
}

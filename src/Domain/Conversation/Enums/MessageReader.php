<?php

declare(strict_types=1);

namespace Domain\Conversation\Enums;

/**
 * Who is reading a handed-off conversation, for read receipts. Each side marks
 * only the other side's messages as read: the visitor reads the operator's
 * replies, the operator reads the visitor's messages. AI replies never carry a
 * read receipt.
 */
enum MessageReader: string
{
    case Visitor = 'visitor';
    case Operator = 'operator';

    /** The role whose messages this reader marks as read. */
    public function reads(): MessageRole
    {
        return match ($this) {
            self::Visitor => MessageRole::Operator,
            self::Operator => MessageRole::User,
        };
    }
}

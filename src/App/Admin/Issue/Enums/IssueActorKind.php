<?php

declare(strict_types=1);

namespace App\Admin\Issue\Enums;

/**
 * Who was behind an issue: a staff account (owner, teammate or admin), a
 * chatter, an API key, a visitor who is not signed in, or the app itself (a
 * scheduled task or a command).
 */
enum IssueActorKind: string
{
    case Staff = 'staff';
    case Chatter = 'chatter';
    case ApiKey = 'api_key';
    case Visitor = 'visitor';
    case System = 'system';

    public function label(): string
    {
        return match ($this) {
            self::Staff => 'Staff',
            self::Chatter => 'Chatter',
            self::ApiKey => 'API key',
            self::Visitor => 'Visitor',
            self::System => 'System',
        };
    }
}

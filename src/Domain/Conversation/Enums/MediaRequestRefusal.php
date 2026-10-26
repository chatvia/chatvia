<?php

declare(strict_types=1);

namespace Domain\Conversation\Enums;

use Illuminate\Support\Str;

/**
 * Why an agent's request for a picture or audio file was turned down before
 * any file was made. Each one has a plain reason the agent passes on to the
 * chatter, so it never says a file is on its way when none is.
 */
enum MediaRequestRefusal: string
{
    case WrongSurface = 'wrong_surface';
    case NotSetUp = 'not_set_up';
    case HourlyLimit = 'hourly_limit';
    case MonthlyLimit = 'monthly_limit';

    /** What the agent tells the chatter, without promising a file. */
    public function reason(GeneratedMediaKind $kind): string
    {
        $files = $kind === GeneratedMediaKind::Image ? 'images' : 'audio files';

        return match ($this) {
            self::WrongSurface => Str::ucfirst($files).' can only be made in the hosted chat. Help the chatter in text instead.',
            self::NotSetUp => "Making {$files} is not set up on this server. Help the chatter in text instead.",
            self::HourlyLimit => "This chat has reached its limit of {$files} for this hour. Tell the chatter to try again later.",
            self::MonthlyLimit => 'This workspace has reached its limit of images and audio files for this month. Tell the chatter no more can be made this month.',
        };
    }
}

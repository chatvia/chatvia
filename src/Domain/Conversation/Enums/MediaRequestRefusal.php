<?php

declare(strict_types=1);

namespace Domain\Conversation\Enums;

use Illuminate\Support\Number;
use Illuminate\Support\Str;

/**
 * Why an agent's request for a picture, audio file or video was turned down before
 * any file was made, including an attached PDF that cannot be read aloud.
 * Each one has a plain reason the agent passes on to the chatter, so it
 * never says a file is on its way when none is.
 */
enum MediaRequestRefusal: string
{
    case WrongSurface = 'wrong_surface';
    case NotSetUp = 'not_set_up';
    case HourlyLimit = 'hourly_limit';
    case MonthlyLimit = 'monthly_limit';
    case NoAttachment = 'no_attachment';
    case NoReadableText = 'no_readable_text';
    case TooLong = 'too_long';

    /** What the agent tells the chatter, without promising a file. */
    public function reason(GeneratedMediaKind $kind): string
    {
        $files = match ($kind) {
            GeneratedMediaKind::Image => 'images',
            GeneratedMediaKind::Audio => 'audio files',
            GeneratedMediaKind::Video => 'videos',
        };

        return match ($this) {
            self::WrongSurface => Str::ucfirst($files).' can only be made in the hosted chat. Help the chatter in text instead.',
            self::NotSetUp => "Making {$files} is not set up on this server. Help the chatter in text instead.",
            self::HourlyLimit => "This chat has reached its limit of {$files} for this hour. Tell the chatter to try again later.",
            self::MonthlyLimit => $kind === GeneratedMediaKind::Video
                ? 'This workspace has reached its limit of videos for this month. Tell the chatter no more can be made this month.'
                : 'This workspace has reached its limit of images and audio files for this month. Tell the chatter no more can be made this month.',
            self::NoAttachment => 'No PDF file is attached to this chat. Ask the chatter to attach the PDF, or pass the words to read as text.',
            self::NoReadableText => 'The attached PDF has no text that can be read: it may be a scan or made of images. Tell the chatter it cannot be read aloud.',
            self::TooLong => 'The attached PDF has more text than one audio file can read ('.Number::format((int) config('chatvia.ai.audio_max_characters')).' characters at most). Offer to read a part of it, or a summary, as text.',
        };
    }
}

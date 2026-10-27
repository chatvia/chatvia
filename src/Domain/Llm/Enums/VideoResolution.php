<?php

declare(strict_types=1);

namespace Domain\Llm\Enums;

/**
 * How sharp the videos are (Settings → AI models → Video generation). 1080p
 * costs more with most services, and some make it only at their longest
 * length.
 */
enum VideoResolution: string
{
    case Hd720 = '720p';
    case Hd1080 = '1080p';
}

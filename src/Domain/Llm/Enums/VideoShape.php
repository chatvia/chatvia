<?php

declare(strict_types=1);

namespace Domain\Llm\Enums;

/**
 * The shape of a video to make: wide or tall, the two every video service
 * makes. Each service names it its own way, most by aspect ratio, Runway by
 * pixel size. The value is stored (`chat_generated_media.shape`).
 */
enum VideoShape: string
{
    case Landscape = 'landscape';
    case Portrait = 'portrait';

    /** The aspect ratio, as most services take it. */
    public function aspectRatio(): string
    {
        return $this === self::Landscape ? '16:9' : '9:16';
    }
}

<?php

declare(strict_types=1);

namespace Domain\Llm\Data\Video;

use Illuminate\Support\Str;
use Spatie\LaravelData\Data;

/**
 * A video a service made, saved to a temp file (a video is too large to keep
 * in memory). The caller owns the file and deletes it once it is stored.
 */
class GeneratedVideoData extends Data
{
    public function __construct(
        public string $path,
        public string $mimeType = 'video/mp4',
    ) {}

    /** The file extension that fits the MIME type: mp4 or webm. */
    public function extension(): string
    {
        return Str::lower($this->mimeType) === 'video/webm' ? 'webm' : 'mp4';
    }
}

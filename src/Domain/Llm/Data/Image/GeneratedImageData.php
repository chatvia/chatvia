<?php

declare(strict_types=1);

namespace Domain\Llm\Data\Image;

use Illuminate\Support\Str;
use Spatie\LaravelData\Data;

/**
 * A picture an image service made: the file's bytes and its MIME type, as
 * the service named it.
 */
class GeneratedImageData extends Data
{
    public function __construct(
        public string $bytes,
        public string $mimeType,
    ) {}

    /** The file extension that fits the MIME type: png, jpg or webp. */
    public function extension(): string
    {
        return match (Str::lower($this->mimeType)) {
            'image/jpeg', 'image/jpg' => 'jpg',
            'image/webp' => 'webp',
            default => 'png',
        };
    }
}

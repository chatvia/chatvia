<?php

declare(strict_types=1);

namespace Domain\Llm\Enums;

use Domain\Llm\Services\ChatFileModel;

/**
 * A kind of file a chatter may attach in a chat, named as the models.dev
 * catalog lists it among a model's inputs (`modalities.input`). The model
 * that answers reads both ({@see ChatFileModel}).
 *
 * Images are the four types every provider that reads images takes; others
 * (HEIC, TIFF, SVG…) are refused by some, so they are not offered.
 */
enum FileInput: string
{
    case Pdf = 'pdf';
    case Image = 'image';

    /** @return list<string> */
    public function mimeTypes(): array
    {
        return match ($this) {
            self::Pdf => ['application/pdf'],
            self::Image => ['image/png', 'image/jpeg', 'image/webp', 'image/gif'],
        };
    }

    /** @return list<string> */
    public function extensions(): array
    {
        return match ($this) {
            self::Pdf => ['pdf'],
            self::Image => ['png', 'jpg', 'jpeg', 'webp', 'gif'],
        };
    }

    /** The input a stored file's MIME type belongs to, if any. */
    public static function forMimeType(string $mimeType): ?self
    {
        return array_find(self::cases(), static fn (self $input): bool => collect($input->mimeTypes())->containsStrict($mimeType));
    }
}

<?php

declare(strict_types=1);

namespace Tests\Support\Knowledge\Document;

/**
 * A real 4×4 PNG image, for tests that upload an image or give a document
 * page a picture.
 */
final class PngImage
{
    /** The image, base64-encoded as a document reader returns page images. */
    public const string BASE64 = 'iVBORw0KGgoAAAANSUhEUgAAAAQAAAAECAIAAAAmkwkpAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAADElEQVQImWNgIB0AAAA0AAEjQ4N1AAAAAElFTkSuQmCC';

    /** The image's bytes. */
    public static function bytes(): string
    {
        return (string) base64_decode(self::BASE64, true);
    }
}

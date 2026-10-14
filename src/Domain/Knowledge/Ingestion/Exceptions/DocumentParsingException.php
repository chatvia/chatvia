<?php

namespace Domain\Knowledge\Ingestion\Exceptions;

use RuntimeException;

/**
 * A document could not be read into text. The message is shown to the owner as
 * the failure reason of the source, so it stays short and free of internals.
 */
class DocumentParsingException extends RuntimeException
{
    public static function imagesNeedOcr(): self
    {
        return new self('Text in images can only be read by an OCR service. Set up a document parser that supports images, or upload a PDF or office file.');
    }

    public static function unsupportedFormat(string $extension): self
    {
        return new self("Files of type .{$extension} cannot be read by the local document parser.");
    }

    public static function unreadableImage(string $extension): self
    {
        return new self("This .{$extension} image could not be opened. Save it as PNG or JPEG and upload it again.");
    }

    public static function failed(string $reason): self
    {
        return new self('The document could not be read: '.$reason);
    }
}

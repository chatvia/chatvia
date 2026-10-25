<?php

declare(strict_types=1);

namespace Domain\Knowledge\Source\File\Data;

use Domain\Knowledge\Source\File\Enums\KnowledgeFileType;
use Domain\Knowledge\Source\File\Services\SourceFileTypes;
use Spatie\LaravelData\Data;
use Symfony\Component\Mime\MimeTypes;

/**
 * A file the owner uploaded as knowledge, described without the HTTP upload
 * object: where its bytes are on this server and what the browser and the
 * server each said about it. The HTTP layer builds it from the request.
 */
final class UploadedSourceFileData extends Data
{
    public function __construct(
        /** Local path of the uploaded bytes (PHP's temporary file, without an extension). */
        public string $path,
        /** The file name on the owner's computer; its extension decides the document format. */
        public string $originalName,
        /** The MIME type detected from the bytes. */
        public string $mimeType,
        /** The MIME type the browser sent; stored on the source as before. */
        public string $clientMimeType,
        public int $sizeInBytes,
    ) {}

    public function extension(): string
    {
        return file_extension($this->originalName);
    }

    /**
     * The extension the stored copy gets. A document keeps its own extension,
     * which is already on the supported list; audio and video take the one
     * their detected type names. It is never guessed from a document's bytes,
     * so a text file holding HTML is not stored as `.html`.
     */
    public function storedExtension(): string
    {
        if (SourceFileTypes::isDocument($this->originalName)) {
            return $this->extension();
        }

        return MimeTypes::getDefault()->getExtensions($this->mimeType)[0] ?? 'bin';
    }

    /**
     * Whether this install accepts it: a document type (by extension) or
     * audio or video (by content) that an admin turned on.
     */
    public function isSupported(): bool
    {
        return SourceFileTypes::isSupported($this->originalName)
            || SourceFileTypes::isSupportedMedia($this->mimeType);
    }

    /**
     * The metering/UI type saved as `filetype`: by extension for documents,
     * by MIME type for audio and video.
     */
    public function category(): KnowledgeFileType
    {
        return SourceFileTypes::isDocument($this->originalName)
            ? SourceFileTypes::category($this->originalName)
            : SourceFileTypes::categoryOfMimeType($this->mimeType);
    }
}

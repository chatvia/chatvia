<?php

declare(strict_types=1);

namespace Domain\Knowledge\Source\File\Data;

use Domain\Knowledge\Source\File\Services\SourceFileTypes;
use Spatie\LaravelData\Data;
use Support\MimeType;

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
     * Whether the knowledge base can ingest it: a document format the document
     * ingestion accepts (by extension) or audio or video (by content).
     */
    public function isSupported(): bool
    {
        return SourceFileTypes::isSupported($this->originalName) || $this->isMedia();
    }

    /**
     * The metering/UI category saved as `filetype`: by extension for documents,
     * by MIME type for audio and video.
     */
    public function category(): string
    {
        return SourceFileTypes::isSupported($this->originalName)
            ? SourceFileTypes::category($this->originalName)
            : SourceFileTypes::categoryOfMimeType($this->mimeType);
    }

    private function isMedia(): bool
    {
        return MimeType::isAudio($this->mimeType) || MimeType::isVideo($this->mimeType);
    }
}

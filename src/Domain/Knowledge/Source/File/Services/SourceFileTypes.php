<?php

namespace Domain\Knowledge\Source\File\Services;

use Domain\Knowledge\Source\File\Enums\KnowledgeFileType;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Support\MimeType;

/**
 * Which files the knowledge base accepts, and the category each one is stored
 * under (`source_files.filetype`).
 *
 * Documents are recognised by their extension, listed in config/mimes.php
 * `ocr`: exotic text formats share generic MIME types (.tex/.bib are
 * text/plain, .fb2 is xml), so the MIME type cannot tell them apart. Audio and
 * video are recognised by their MIME type, which is reliable for them.
 *
 * What the ingestion can read is fixed; what this install accepts is the
 * subset an admin turned on ({@see KnowledgeFileType}). Files already
 * uploaded keep working when their type is turned off.
 */
final class SourceFileTypes
{
    /**
     * Every extension the document ingestion can read, accepted or not.
     *
     * @return list<string>
     */
    public static function documentExtensions(): array
    {
        /** @var array<string, list<string>> $groups */
        $groups = config('mimes.ocr');

        return array_values(array_unique(array_merge(...array_values($groups))));
    }

    /** Whether the document ingestion can read the file, judged by its extension. */
    public static function isDocument(UploadedFile|string $file): bool
    {
        return collect(self::documentExtensions())->containsStrict(file_extension($file));
    }

    /**
     * The document extensions this install accepts: those of the document
     * types an admin turned on, that this server can read
     * ({@see KnowledgeFileType::readableExtensions()}).
     *
     * @return list<string>
     */
    public static function supportedExtensions(): array
    {
        $documents = array_filter(KnowledgeFileType::enabled(), fn (KnowledgeFileType $type): bool => ! $type->isMedia());

        return array_values(array_unique(array_merge([], ...array_map(
            fn (KnowledgeFileType $type): array => $type->readableExtensions(),
            array_values($documents),
        ))));
    }

    /** Whether this install accepts the document, judged by its extension. */
    public static function isSupported(UploadedFile|string $file): bool
    {
        return collect(self::supportedExtensions())->containsStrict(file_extension($file));
    }

    /** Whether this install accepts audio or video of this MIME type. */
    public static function isSupportedMedia(string $mimeType): bool
    {
        return (MimeType::isAudio($mimeType) && KnowledgeFileType::Audio->isEnabled())
            || (MimeType::isVideo($mimeType) && KnowledgeFileType::Video->isEnabled());
    }

    /** Whether the file is an image, judged by its extension. */
    public static function isImage(UploadedFile|string $file): bool
    {
        return collect(config()->array('mimes.ocr.image'))->containsStrict(file_extension($file));
    }

    /**
     * The type stored as `filetype`: by extension for documents, and by
     * MIME type for everything else (audio and video).
     *
     * @throws RuntimeException when the MIME type is not a known category
     */
    public static function category(UploadedFile|string $file): KnowledgeFileType
    {
        return match (file_extension($file)) {
            'pdf' => KnowledgeFileType::Pdf,
            'doc', 'docx', 'rtf' => KnowledgeFileType::Word,
            'ppt', 'pptx' => KnowledgeFileType::Presentation,
            'xlsx', 'csv' => KnowledgeFileType::Spreadsheet,
            'txt', 'md', 'markdown' => KnowledgeFileType::Text,
            'jpg', 'jpeg', 'png', 'avif', 'tiff', 'tif', 'gif', 'heic', 'heif', 'bmp', 'webp' => KnowledgeFileType::Image,
            'epub', 'fb2' => KnowledgeFileType::Ebook,
            default => self::categoryOfMimeType(
                (string) ($file instanceof UploadedFile ? $file->getMimeType() : File::mimeType($file)),
            ),
        };
    }

    /**
     * The type for a MIME type (see {@see MimeType::category()}).
     *
     * @throws RuntimeException when the MIME type is not a known category
     */
    public static function categoryOfMimeType(string $mimeType): KnowledgeFileType
    {
        $slug = MimeType::category($mimeType)
            ?? throw new RuntimeException('Unsupported mime type');

        return KnowledgeFileType::tryFrom($slug)
            ?? throw new RuntimeException('Unsupported mime type');
    }
}

<?php

namespace Domain\Knowledge\Source\File\Services;

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
 */
final class SourceFileTypes
{
    /**
     * Every extension the document ingestion accepts.
     *
     * @return list<string>
     */
    public static function supportedExtensions(): array
    {
        /** @var array<string, list<string>> $groups */
        $groups = config('mimes.ocr');

        return array_values(array_unique(array_merge(...array_values($groups))));
    }

    /** Whether the document ingestion accepts the file, judged by its extension. */
    public static function isSupported(UploadedFile|string $file): bool
    {
        return in_array(file_extension($file), self::supportedExtensions(), true);
    }

    /** Whether the file is an image, judged by its extension. */
    public static function isImage(UploadedFile|string $file): bool
    {
        return in_array(file_extension($file), config('mimes.ocr.image'), true);
    }

    /**
     * The category stored as `filetype`: by extension for documents, and by
     * MIME type for everything else (audio and video).
     *
     * @throws RuntimeException when the MIME type is not a known category
     */
    public static function category(UploadedFile|string $file): string
    {
        return match (file_extension($file)) {
            'pdf' => 'pdf',
            'doc', 'docx', 'rtf' => 'word',
            'ppt', 'pptx' => 'presentation',
            'xlsx', 'csv' => 'spreadsheet',
            'txt' => 'text',
            'md', 'markdown' => 'markdown',
            'jpg', 'jpeg', 'png', 'avif', 'tiff', 'tif', 'gif', 'heic', 'heif', 'bmp', 'webp' => 'image',
            'epub', 'fb2' => 'document',
            default => self::categoryOfMimeType(
                (string) ($file instanceof UploadedFile ? $file->getMimeType() : File::mimeType($file)),
            ),
        };
    }

    /**
     * The category for a MIME type (see {@see MimeType::category()}).
     *
     * @throws RuntimeException when the MIME type is not a known category
     */
    public static function categoryOfMimeType(string $mimeType): string
    {
        return MimeType::category($mimeType)
            ?? throw new RuntimeException('Unsupported mime type');
    }
}

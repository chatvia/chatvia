<?php

namespace Support;

/**
 * Single source of truth for mime-type group membership. Reads from
 * config/mimes.php so adding a new mime in one place reaches all readers
 * (Agent::pdfFiles() relation, SourceFile::isPdfFile() predicate, the
 * get_file_type() helper, controller filters).
 */
class MimeType
{
    public static function isWord(string $mimeType): bool
    {
        return self::matches($mimeType, 'word');
    }

    public static function isPdf(string $mimeType): bool
    {
        return self::matches($mimeType, 'pdf');
    }

    public static function isText(string $mimeType): bool
    {
        return self::matches($mimeType, 'text');
    }

    public static function isImage(string $mimeType): bool
    {
        return self::matches($mimeType, 'images');
    }

    public static function isSpreadsheet(string $mimeType): bool
    {
        return self::matches($mimeType, 'spreadsheets');
    }

    public static function isPresentation(string $mimeType): bool
    {
        return self::matches($mimeType, 'presentation');
    }

    public static function isAudio(string $mimeType): bool
    {
        return self::matches($mimeType, 'audio');
    }

    public static function isVideo(string $mimeType): bool
    {
        return self::matches($mimeType, 'video');
    }

    /**
     * Resolve a mime type into the canonical category slug used by SourceFile::filetype.
     * Returns null when the type is unrecognised.
     */
    public static function category(string $mimeType): ?string
    {
        foreach (self::CATEGORIES as $category => $configKey) {
            if (self::matches($mimeType, $configKey)) {
                return $category;
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    public static function group(string $configKey): array
    {
        /** @var array<int, string>|null $group */
        $group = config("mimes.{$configKey}");

        return $group ?? [];
    }

    private const CATEGORIES = [
        'pdf' => 'pdf',
        'word' => 'word',
        'text' => 'text',
        'presentation' => 'presentation',
        'image' => 'images',
        'spreadsheet' => 'spreadsheets',
        'audio' => 'audio',
        'video' => 'video',
    ];

    private static function matches(string $mimeType, string $configKey): bool
    {
        return in_array($mimeType, self::group($configKey), true);
    }
}

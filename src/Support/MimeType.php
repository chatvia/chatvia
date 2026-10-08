<?php

namespace Support;

/**
 * Single source of truth for mime-type group membership. Reads from
 * config/mimes.php so adding a new mime in one place reaches all readers
 * (Agent::pdfFiles() relation, SourceFile::isPdfFile() predicate, the
 * get_file_type()/is_audio_file()/is_video_file() helpers, controller
 * filters).
 */
class MimeType
{
    private const array CATEGORIES = [
        'pdf' => 'pdf',
        'word' => 'word',
        'text' => 'text',
        'presentation' => 'presentation',
        'image' => 'images',
        'spreadsheet' => 'spreadsheets',
        'audio' => 'audio',
        'video' => 'video',
    ];

    public static function isWord(string $mimeType): bool
    {
        return self::matches($mimeType, 'word');
    }

    public static function isPdf(string $mimeType): bool
    {
        return self::matches($mimeType, 'pdf');
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
        return array_find_key(self::CATEGORIES, fn ($configKey): bool => self::matches($mimeType, $configKey));
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

    private static function matches(string $mimeType, string $configKey): bool
    {
        return in_array($mimeType, self::group($configKey), true);
    }
}

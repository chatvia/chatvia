<?php

namespace Support;

/**
 * Single source of truth for mime-type group membership. Reads from
 * config/mimes.php so adding a new mime in one place reaches all readers
 * (the is_pdf_file()/is_audio_file()/is_video_file() helpers, the knowledge
 * base's file categories, model relations and controller filters).
 */
final class MimeType
{
    /** Category slug => config/mimes.php group key. */
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
     * Resolve a mime type into its category slug (pdf, word, text,
     * presentation, image, spreadsheet, audio, video). Returns null when the
     * type is unrecognised.
     */
    public static function category(string $mimeType): ?string
    {
        return array_find_key(self::CATEGORIES, fn (string $configKey): bool => self::matches($mimeType, $configKey));
    }

    /**
     * The MIME types of one config/mimes.php group (empty for an unknown key).
     *
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

<?php

declare(strict_types=1);

namespace Domain\Knowledge\Document\Conversion\Markdown;

/**
 * Decodes 8-bit text stored in a Windows (or old Mac) code page, as RTF
 * files and Word 6 and 95 documents store it, into UTF-8. Double-byte code
 * pages (Japanese, Chinese, Korean) are decoded as a whole, so a character
 * split over two bytes stays one character.
 */
final class CodePage
{
    /** The code pages mbstring decodes itself; iconv decodes the others. */
    private const array MBSTRING = [
        932 => 'CP932', 936 => 'CP936', 949 => 'UHC', 950 => 'CP950', 1251 => 'CP1251', 1252 => 'CP1252',
        1254 => 'CP1254', 850 => 'CP850', 866 => 'CP866', 65001 => 'UTF-8',
    ];

    private const array ICONV = [437, 874, 1250, 1253, 1255, 1256, 1257, 1258];

    private const int MAC_ROMAN = 10000;

    public static function toUtf8(string $bytes, int $codePage): string
    {
        if ($bytes === '' || mb_check_encoding($bytes, 'ASCII')) {
            return $bytes;
        }

        if (isset(self::MBSTRING[$codePage])) {
            return mb_convert_encoding($bytes, 'UTF-8', self::MBSTRING[$codePage]);
        }

        if (collect(self::ICONV)->containsStrict($codePage) || $codePage === self::MAC_ROMAN) {
            $text = @iconv($codePage === self::MAC_ROMAN ? 'MACINTOSH' : "CP{$codePage}", 'UTF-8//IGNORE', $bytes);

            if ($text !== false) {
                return $text;
            }
        }

        return mb_convert_encoding($bytes, 'UTF-8', 'CP1252');
    }

    /**
     * The code page Windows uses for a language (a Windows language id),
     * for files that name their language but not their code page.
     */
    public static function forLanguage(int $languageId): int
    {
        return match ($languageId & 0x03FF) {
            0x19, 0x22, 0x02, 0x23, 0x2F, 0x3F, 0x40, 0x44, 0x50 => 1251,
            0x05, 0x15, 0x0E, 0x1B, 0x24, 0x1A, 0x18, 0x1C => 1250,
            0x08 => 1253,
            0x1F, 0x2C, 0x43 => 1254,
            0x0D => 1255,
            0x01, 0x20, 0x29 => 1256,
            0x25, 0x26, 0x27 => 1257,
            0x2A => 1258,
            0x1E => 874,
            0x11 => 932,
            0x12 => 949,
            0x04 => collect([0x0404, 0x0C04, 0x1404])->containsStrict($languageId) ? 950 : 936,
            default => 1252,
        };
    }
}

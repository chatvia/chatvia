<?php

namespace Support\Text;

/**
 * UTF-8-safe string helpers. A plain byte cut (`substr`) can split a multibyte
 * character (Arabic, emoji, CJK) and leave invalid UTF-8, which Postgres
 * rejects on insert and json_encode() refuses to encode.
 */
final class Utf8
{
    /**
     * Cut $text to at most $maxBytes bytes without splitting a character.
     */
    public static function truncateBytes(string $text, int $maxBytes): string
    {
        if (strlen($text) <= $maxBytes) {
            return $text;
        }

        return mb_strcut($text, 0, max(0, $maxBytes), 'UTF-8');
    }
}

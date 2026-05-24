<?php

namespace Domain\Ingestion\Services\Tidy;

use Normalizer;

class TextCleaner
{
    public static function clean(string $text): string
    {
        // 1) Remove NULL bytes
        $s = str_replace("\0", '', $text);

        // 2) If it's already valid UTF-8, keep it; otherwise try to fix common encodings
        if (! mb_detect_encoding($s, 'UTF-8', true)) {
            // Try Windows-1252 and ISO-8859-1 first (common culprits), then fall back
            $s = mb_convert_encoding($s, 'UTF-8', 'Windows-1252, ISO-8859-1, UTF-16, UTF-16LE, UTF-16BE, UTF-32, ASCII');
        }

        // 3) As a last resort, strip/replace any remaining invalid UTF-8 sequences
        //    - iconv will drop invalid bytes with //IGNORE
        $s = @iconv('UTF-8', 'UTF-8//IGNORE', $s);

        // 4) Normalize to NFC to avoid weird composed/compatibility forms
        if (! Normalizer::isNormalized($s, Normalizer::FORM_C)) {
            $s = Normalizer::normalize($s, Normalizer::FORM_C);
        }

        // 5) Remove control characters (C0/C1), but keep \n, \r, \t if you want
        //    Here we keep tabs/newlines; remove \n\r\t from the class if you want them gone too.
        $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F\x{0080}-\x{009F}]/u', '', $s);

        // 6) Trim weird spaces
        $s = preg_replace('/[ \x{00A0}\x{2000}-\x{200B}\x{202F}\x{205F}\x{3000}]+/u', ' ', $s);

        return trim($s);
    }
}

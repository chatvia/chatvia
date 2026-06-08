<?php

namespace Domain\Guardrail\Services\Detectors;

/**
 * Rules-based PII detection + redaction. Deterministic, instant, and free — no
 * LLM call. Catches the high-risk identifiers an AI agent must never echo or
 * leak: email addresses, phone numbers, credit-card numbers (Luhn-validated to
 * avoid masking ordinary long digit strings), and US-style SSNs.
 *
 * Redaction order matters: emails and structured numbers (card, SSN) are masked
 * before the looser phone pattern so it can't swallow their digits.
 */
class PiiDetector
{
    private const EMAIL = '/[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}/';

    private const SSN = '/\b\d{3}-\d{2}-\d{4}\b/';

    // Candidate card: 13–19 digits with optional space/dash grouping.
    private const CARD = '/\b(?:\d[ \-]?){13,19}\b/';

    // Candidate phone: a +prefix or digit, then 7+ more digit/separator chars.
    private const PHONE = '/(?<!\w)\+?\d[\d().\-\s]{7,}\d(?!\w)/';

    /**
     * Redact every PII span in the text. Returns the masked text, the distinct
     * PII categories found, and the total number of spans masked.
     *
     * @return array{text: string, types: list<string>, count: int}
     */
    public function redact(string $text): array
    {
        $types = [];
        $count = 0;

        $mask = function (string $pattern, string $token, string $type, ?callable $accept = null) use (&$text, &$types, &$count): void {
            $hits = 0;
            $text = (string) preg_replace_callback($pattern, function (array $m) use ($token, $accept, &$hits): string {
                if ($accept !== null && ! $accept($m[0])) {
                    return $m[0];
                }
                $hits++;

                return $token;
            }, $text);

            if ($hits > 0) {
                $types[] = $type;
                $count += $hits;
            }
        };

        $mask(self::EMAIL, '[redacted-email]', 'email');
        $mask(self::SSN, '[redacted-ssn]', 'ssn');
        $mask(self::CARD, '[redacted-card]', 'card', fn (string $s): bool => $this->isCard($s));
        $mask(self::PHONE, '[redacted-phone]', 'phone', fn (string $s): bool => $this->isPhone($s));

        return ['text' => $text, 'types' => array_values(array_unique($types)), 'count' => $count];
    }

    /**
     * A digit run is a card only if it has 13–19 digits AND passes the Luhn
     * checksum — this rejects order numbers, IDs, and other long integers.
     */
    private function isCard(string $candidate): bool
    {
        $digits = preg_replace('/\D/', '', $candidate) ?? '';
        $len = strlen($digits);

        return $len >= 13 && $len <= 19 && $this->luhn($digits);
    }

    private function luhn(string $digits): bool
    {
        $sum = 0;
        $alt = false;
        for ($i = strlen($digits) - 1; $i >= 0; $i--) {
            $n = (int) $digits[$i];
            if ($alt) {
                $n *= 2;
                if ($n > 9) {
                    $n -= 9;
                }
            }
            $sum += $n;
            $alt = ! $alt;
        }

        return $sum % 10 === 0;
    }

    /**
     * Require 9–15 digits so the loose pattern doesn't mask short numbers; the
     * card pass has already consumed (and removed) any real card numbers.
     */
    private function isPhone(string $candidate): bool
    {
        $digits = preg_replace('/\D/', '', $candidate) ?? '';
        $len = strlen($digits);

        return $len >= 9 && $len <= 15;
    }
}

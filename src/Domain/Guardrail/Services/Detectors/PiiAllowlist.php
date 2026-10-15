<?php

declare(strict_types=1);

namespace Domain\Guardrail\Services\Detectors;

/**
 * Values the PII filters must leave as they are: the business's own contact
 * details and the owner's "never mask" list. Without it the agent could not
 * give out the support email or phone number it was told to share.
 *
 * An entry with an `@` is an email address, or a whole email domain when it
 * starts with `@` (`@acme.com`). Any other entry is compared on its digits,
 * so `+49 30 1234567` also allows `030 1234567` (the same number written
 * nationally) and `+1 800 555 1234` allows `800-555-1234`.
 */
final readonly class PiiAllowlist
{
    /** Fewest digits a number entry needs, so a short entry cannot allow every number. */
    private const int MIN_DIGITS = 7;

    /** @var list<string> lower-case addresses and `@domain` entries */
    private array $emails;

    /** @var list<string> digits, without leading zeros */
    private array $numbers;

    /**
     * @param  list<string>  $values
     */
    public function __construct(array $values = [])
    {
        $emails = [];
        $numbers = [];

        foreach ($values as $value) {
            $value = strtolower(trim($value));

            if (str_contains($value, '@')) {
                $emails[] = $value;

                continue;
            }

            $digits = $this->significantDigits($value);

            if (strlen($digits) >= self::MIN_DIGITS) {
                $numbers[] = $digits;
            }
        }

        $this->emails = $emails;
        $this->numbers = $numbers;
    }

    public function allows(string $value): bool
    {
        if (str_contains($value, '@')) {
            $email = strtolower($value);

            return array_any($this->emails, static fn (string $entry): bool => $entry === $email
                || (str_starts_with($entry, '@') && str_ends_with($email, $entry)));
        }

        $digits = $this->significantDigits($value);

        if (strlen($digits) < self::MIN_DIGITS) {
            return false;
        }

        // The same number with or without its country code.
        return array_any($this->numbers, static fn (string $entry): bool => str_ends_with($digits, $entry)
            || str_ends_with($entry, $digits));
    }

    /** The digits without the leading zeros of a trunk or `00` prefix. */
    private function significantDigits(string $value): string
    {
        return ltrim(preg_replace('/\D/', '', $value) ?? '', '0');
    }
}

<?php

namespace Support\Cms\Marketing;

/**
 * The legal entity that runs this install, read from `chatvia.company`. The
 * public legal pages, the DPA and the site footer render it. Every part is
 * optional: a view hides the line whose value is missing, so a self-hosted
 * install shows nothing instead of someone else's company details.
 */
final class CompanyDetails
{
    public static function name(): ?string
    {
        return self::value('name');
    }

    public static function email(): ?string
    {
        return self::value('email');
    }

    public static function vatId(): ?string
    {
        return self::value('vat_id');
    }

    /**
     * The address split into display lines. COMPANY_ADDRESS separates lines
     * with a newline ("\n" inside a double-quoted .env value).
     *
     * @return list<string>
     */
    public static function addressLines(): array
    {
        $address = self::value('address');

        if ($address === null) {
            return [];
        }

        $lines = preg_split('/\R|\\\\n/', $address) ?: [];

        return array_values(array_filter(array_map('trim', $lines), fn (string $line): bool => $line !== ''));
    }

    /** The address on one line, e.g. for a sentence or a PDF header. */
    public static function inlineAddress(): ?string
    {
        $lines = self::addressLines();

        return $lines === [] ? null : implode(', ', $lines);
    }

    /** True when no company detail is configured at all. */
    public static function isEmpty(): bool
    {
        return self::name() === null
            && self::email() === null
            && self::vatId() === null
            && self::addressLines() === [];
    }

    private static function value(string $key): ?string
    {
        $value = config("chatvia.company.{$key}");

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return trim($value);
    }
}

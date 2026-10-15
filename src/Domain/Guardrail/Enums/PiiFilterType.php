<?php

namespace Domain\Guardrail\Enums;

/**
 * The catalog of selectable PII filters. Each case is one redaction filter the
 * workspace can toggle per agent (stored in the `guardrails.filters` setting).
 *
 * Declaration order IS the pipeline order: email and the structured numbers run
 * before the looser phone pattern so it can't swallow their digits (IBAN even
 * before cards, whose pattern would take an IBAN's digits), and the digit-only
 * national IDs (Danish CPR) run before phone for the same reason.
 * PiiDetector iterates these cases to build its filter pipeline.
 */
enum PiiFilterType: string
{
    case Email = 'email';
    case Iban = 'iban';
    case CreditCard = 'credit_card';
    case UsSsn = 'us_ssn';
    case GermanSsn = 'german_ssn';
    case UkNino = 'uk_nino';
    case DanishCpr = 'danish_cpr';
    case IpAddress = 'ip_address';
    case Phone = 'phone';

    public function label(): string
    {
        return match ($this) {
            self::Email => 'Email address',
            self::Iban => 'Bank account (IBAN)',
            self::CreditCard => 'Credit card',
            self::UsSsn => 'US Social Security Number',
            self::GermanSsn => 'German social-insurance number',
            self::UkNino => 'UK National Insurance number',
            self::DanishCpr => 'Danish CPR number',
            self::IpAddress => 'IP address',
            self::Phone => 'Phone number',
        };
    }

    /**
     * On for a freshly-enabled agent. The common identifiers (email, IBAN,
     * card, US SSN, phone) are on; region-specific national IDs are opt-in
     * (they overlap with ordinary numbers, so a workspace should choose them
     * deliberately), and so are IP addresses, which technical support often
     * needs.
     */
    public function enabledByDefault(): bool
    {
        return match ($this) {
            self::Email, self::Iban, self::CreditCard, self::UsSsn, self::Phone => true,
            self::GermanSsn, self::UkNino, self::DanishCpr, self::IpAddress => false,
        };
    }

    /**
     * @return list<string> default-enabled keys
     */
    public static function defaults(): array
    {
        return array_values(array_map(
            fn (self $t): string => $t->value,
            array_filter(self::cases(), fn (self $t): bool => $t->enabledByDefault()),
        ));
    }

    /**
     * @return list<string> every valid filter key
     */
    public static function keys(): array
    {
        return array_map(fn (self $t): string => $t->value, self::cases());
    }

    /**
     * UI catalog in display order.
     *
     * @return list<array{key: string, label: string}>
     */
    public static function catalog(): array
    {
        return array_map(fn (self $t): array => ['key' => $t->value, 'label' => $t->label()], self::cases());
    }
}

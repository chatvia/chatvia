<?php

namespace Domain\Guardrail\Services\Detectors;

use Domain\Guardrail\Data\PiiRedactionData;
use Domain\Guardrail\Enums\PiiFilterType;
use Domain\Guardrail\Services\Detectors\Filters\CardFilter;
use Domain\Guardrail\Services\Detectors\Filters\DanishCprFilter;
use Domain\Guardrail\Services\Detectors\Filters\EmailFilter;
use Domain\Guardrail\Services\Detectors\Filters\GermanSsnFilter;
use Domain\Guardrail\Services\Detectors\Filters\PhoneFilter;
use Domain\Guardrail\Services\Detectors\Filters\UsSsnFilter;
use Illuminate\Pipeline\Pipeline;

/**
 * Rules-based PII detection + redaction. Deterministic, instant, and free — no
 * LLM call. Catches the high-risk identifiers an AI agent must never echo or
 * leak: emails, phone numbers, credit cards (Luhn-validated), US SSNs, German
 * social-insurance numbers, and Danish CPR numbers. These are all *structured*
 * identifiers with rigid formats, so regex (+ checksum/date validation) is the
 * right tool — an LLM/NER pass is only worth it for unstructured PII like names
 * or addresses, which this detector does not target.
 *
 * Each identifier is a dedicated {@see Filters\PiiFilter} run through a pipeline.
 * The set that runs is chosen per agent (see PiiFilterType / the guardrails.filters
 * setting); the canonical order comes from PiiFilterType's case order — emails and
 * structured numbers are masked before the looser phone pattern so it can't
 * swallow their digits.
 *
 * Each span is replaced with a typed, numbered placeholder ([EMAIL_1], [PHONE_2],
 * …). Distinct values get distinct numbers so the model can tell two emails apart,
 * and a repeated value reuses its number so coreference survives ("mail X … resend
 * to X" → both become [EMAIL_1]). This matches the placeholder convention used by
 * Presidio / LLM-gateway redactors.
 */
class PiiDetector
{
    /**
     * Redact PII spans in the text. `$only` restricts redaction to those filter
     * keys (see {@see PiiFilterType}); null runs every filter. Returns the masked
     * text, the distinct PII categories found, and the total spans masked.
     *
     * @param  list<string>|null  $only
     */
    public function redact(string $text, ?array $only = null): PiiRedactionData
    {
        $filters = [];
        foreach (PiiFilterType::cases() as $type) {
            if ($only === null || in_array($type->value, $only, true)) {
                $filters[] = $this->filterClass($type);
            }
        }

        if ($filters === []) {
            return new PiiRedactionData($text);
        }

        return app(Pipeline::class)
            ->send(new PiiRedactionState($text))
            ->through($filters)
            ->then(fn (PiiRedactionState $state): PiiRedactionData => $state->result());
    }

    /**
     * @return class-string<Filters\PiiFilter>
     */
    private function filterClass(PiiFilterType $type): string
    {
        return match ($type) {
            PiiFilterType::Email => EmailFilter::class,
            PiiFilterType::CreditCard => CardFilter::class,
            PiiFilterType::UsSsn => UsSsnFilter::class,
            PiiFilterType::GermanSsn => GermanSsnFilter::class,
            PiiFilterType::DanishCpr => DanishCprFilter::class,
            PiiFilterType::Phone => PhoneFilter::class,
        };
    }
}

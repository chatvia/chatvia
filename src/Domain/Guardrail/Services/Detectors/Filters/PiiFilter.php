<?php

namespace Domain\Guardrail\Services\Detectors\Filters;

use Closure;
use Domain\Guardrail\Services\Detectors\PiiRedactionState;

/**
 * One PII entity type in the redaction pipeline. Subclasses declare the regex,
 * the placeholder label ([EMAIL_…]), and the verdict type; the base wires them
 * into the shared masking pass. Override {@see accepts()} to validate a candidate
 * (e.g. a Luhn check) before it is masked.
 *
 * Pipeline order matters — see PiiDetector::$filters.
 */
abstract class PiiFilter
{
    abstract protected function pattern(): string;

    /** Uppercase placeholder label, e.g. EMAIL → [EMAIL_1]. */
    abstract protected function label(): string;

    /** Verdict type recorded when this filter fires, e.g. "email". */
    abstract protected function type(): string;

    /** Gate a raw match before masking. Default: accept every match. */
    protected function accepts(string $value): bool
    {
        return true;
    }

    public function handle(PiiRedactionState $state, Closure $next): mixed
    {
        $state->mask($this->pattern(), $this->label(), $this->type(), fn (string $value): bool => $this->accepts($value));

        return $next($state);
    }
}

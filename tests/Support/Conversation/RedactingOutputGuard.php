<?php

declare(strict_types=1);

namespace Tests\Support\Conversation;

use Domain\Agent\Models\Agent;
use Domain\Guardrail\Data\GuardResultData;
use Domain\Guardrail\Data\GuardVerdictData;
use Domain\Guardrail\Enums\GuardOutcome;
use Domain\Guardrail\Enums\GuardStage;
use Domain\Guardrail\Enums\GuardType;
use Domain\Guardrail\Services\GuardrailInspector;
use Illuminate\Support\Str;

/**
 * The output guardrails without a classifier: an answer holding one of the
 * given texts comes back with it replaced, as a PII redaction, and any other
 * answer comes back unchanged.
 */
final class RedactingOutputGuard extends GuardrailInspector
{
    /** @param  array<string, string>  $replacements  text => what it is replaced with */
    public function __construct(private readonly array $replacements = [])
    {
        // No parent constructor: no guard runs.
    }

    public function inspectOutput(Agent $agent, string $text, ?string $language = null): GuardResultData
    {
        if (! Str::contains($text, array_keys($this->replacements))) {
            return GuardResultData::clean($text);
        }

        return new GuardResultData(
            text: Str::replace(array_keys($this->replacements), array_values($this->replacements), $text),
            verdicts: [new GuardVerdictData(GuardType::Pii, GuardStage::Output, GuardOutcome::Redacted)],
        );
    }
}

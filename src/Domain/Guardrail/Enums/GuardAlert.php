<?php

declare(strict_types=1);

namespace Domain\Guardrail\Enums;

use Domain\Guardrail\Events\GuardrailTriggered;

/**
 * The guardrail triggers worth telling the owner about: a blocked attack (a
 * prompt injection or a leaked system prompt) and a check that could not run.
 * Ordinary findings — masked personal data, a refused topic — are not alerts;
 * the Activity log has them.
 */
enum GuardAlert: string
{
    case Attack = 'attack';
    case Skipped = 'skipped';

    public static function for(GuardrailTriggered $event): ?self
    {
        $type = GuardType::tryFrom($event->guardType);
        $outcome = GuardOutcome::tryFrom($event->outcome);

        return match (true) {
            // One failed classifier call skips several checks; only the one
            // it marks is an alert, so the count is of failed calls.
            $outcome === GuardOutcome::Skipped => ($event->detail['alert'] ?? false) === true ? self::Skipped : null,
            $outcome === GuardOutcome::Blocked && collect([GuardType::PromptInjection, GuardType::PromptLeak])->containsStrict($type) => self::Attack,
            default => null,
        };
    }
}

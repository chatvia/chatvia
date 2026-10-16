<?php

declare(strict_types=1);

namespace Domain\Guardrail\Events;

use Domain\Guardrail\Listeners\RecordGuardrailEvent;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired once per guard that triggered on a turn (PII redaction, injection block,
 * off-topic refusal, toxicity). Carries the denormalized identity + the offending
 * sample so the queued {@see RecordGuardrailEvent}
 * listener can write an immutable `guardrail_events` row off the hot path.
 */
class GuardrailTriggered
{
    use Dispatchable;

    /**
     * @param  array<string, mixed>  $detail
     */
    public function __construct(
        public readonly ?int $agentId,
        public readonly string $agentName,
        public readonly int $workspaceId,
        public readonly ?int $userId,
        public readonly ?string $userName,
        public readonly ?string $conversationExternalId,
        public readonly ?string $messageId,
        public readonly ?string $traceId,
        public readonly string $stage,
        public readonly string $guardType,
        public readonly string $outcome,
        public readonly ?string $severity,
        public readonly array $detail,
        public readonly string $sample,
        // The chatter whose turn tripped the guard; `userId` is set instead
        // for a staff account. Both null for an anonymous visitor.
        public readonly ?int $chatterId = null,
        // An anonymous visitor's fingerprint (VisitorFingerprint), so repeat
        // attacks can be seen and the visitor blocked.
        public readonly ?string $visitorKey = null,
    ) {}
}

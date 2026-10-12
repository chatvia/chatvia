<?php

namespace Domain\Guardrail\Actions;

use Domain\Guardrail\Events\GuardrailTriggered;
use Domain\Guardrail\Listeners\RecordGuardrailEvent;
use Domain\Guardrail\Models\GuardrailEvent;
use Support\Text\Utf8;

/**
 * Writes one immutable `guardrail_events` row for a fired guard. Hashes the full
 * original sample (sha256 — integrity that survives truncation), then stores only
 * a size-capped snippet so the safety log can never itself become a PII sink.
 * Invoked from the queued {@see RecordGuardrailEvent}
 * listener, off the streaming hot path.
 */
class RecordGuardrailEventAction
{
    public function execute(GuardrailTriggered $event): void
    {
        [$snippet, $sha] = $this->cap($event->sample);

        GuardrailEvent::create([
            'agent_id' => $event->agentId,
            'agent_name' => $event->agentName,
            'workspace_id' => $event->workspaceId,
            'user_id' => $event->userId,
            'chatter_id' => $event->chatterId,
            'user_name' => $event->userName,
            'conversation_external_id' => $event->conversationExternalId,
            'message_id' => $event->messageId,
            'trace_id' => $event->traceId,
            'stage' => $event->stage,
            'guard_type' => $event->guardType,
            'outcome' => $event->outcome,
            'severity' => $event->severity,
            'detail' => $event->detail,
            'snippet' => $snippet,
            'snippet_sha256' => $sha,
        ]);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function cap(string $sample): array
    {
        $sha = hash('sha256', $sample);
        $max = (int) config('chatvia.guardrails.max_snippet_bytes', 2048);
        // Byte cap, cut on a character boundary so the stored text stays valid UTF-8.
        $snippet = Utf8::truncateBytes($sample, $max);

        return [$snippet, $sha];
    }
}

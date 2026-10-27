<?php

namespace Domain\Conversation\Actions\Tools;

use Domain\Conversation\Events\ToolCallCompleted;
use Domain\Conversation\Listeners\RecordToolCallAudit;
use Domain\Conversation\Models\AgentActivity;
use Illuminate\Support\Str;
use Support\Text\Utf8;

/**
 * Writes one immutable governance audit row (`agent_activities`) for a completed
 * tool-call decision. Invoked from the queued
 * {@see RecordToolCallAudit} listener, off the
 * streaming hot path.
 *
 * Before storage it hashes the FULL raw arguments (sha256 — tamper-evidence that
 * survives redaction/truncation), then redacts configured sensitive keys and
 * size-caps args + result so the audit can never become a PII/secret sink. The
 * agent/user names + connector are already denormalized on the event, so the
 * row stays readable after the agent or user is deleted.
 *
 * Arguments that fit and are a JSON object or list go in the jsonb `arguments`
 * column. Anything else (cut at the cap, or not JSON at all) goes in the text
 * `arguments_preview` column instead, because a cut JSON string is no longer
 * an array and the readers expect one.
 */
class RecordToolCallAuditAction
{
    /** Longest decision note stored, in bytes. */
    private const int MAX_NOTE_BYTES = 1000;

    public function execute(ToolCallCompleted $event): void
    {
        [$arguments, $argumentsPreview, $argsTruncated] = $this->redactAndCapArguments($event->arguments);
        [$result, $resultTruncated] = $this->cap($event->resultText, (int) config('chatvia.governance.max_result_bytes', 8192));

        AgentActivity::query()->create([
            'agent_id' => $event->agentId,
            'agent_name' => $event->agentName,
            'workspace_id' => $event->workspaceId,
            'user_id' => $event->userId,
            'chatter_id' => $event->chatterId,
            'user_name' => $event->userName,
            'conversation_external_id' => $event->conversationExternalId,
            'trace_id' => $event->traceId,
            'request_id' => $event->requestId,
            'tool_call_id' => $event->toolCallId,
            'tool_name' => $event->toolName,
            'tool_kind' => $event->toolKind,
            'connector_slug' => $event->connectorSlug,
            'connector_name' => $event->connectorName,
            'outcome' => $event->approvalOutcome,
            'is_gated' => $event->gated,
            'is_error' => $event->isError,
            'latency_ms' => $event->latencyMs,
            'arguments' => $arguments,
            'arguments_preview' => $argumentsPreview,
            'arguments_sha256' => hash('sha256', $event->arguments),
            'arguments_truncated' => $argsTruncated,
            'result_text' => $result,
            'result_truncated' => $resultTruncated,
            'decided_at' => $event->decidedAt,
            'decision_note' => $this->cap($event->decisionNote, self::MAX_NOTE_BYTES)[0],
        ]);
    }

    /**
     * Hash-safe redaction + size cap of the raw JSON args. Returns the
     * redacted array when it is JSON and fits, else null plus the (possibly
     * cut) text for the preview column, and whether it was cut.
     *
     * @return array{0: array<array-key, mixed>|null, 1: ?string, 2: bool}
     */
    private function redactAndCapArguments(string $raw): array
    {
        $max = (int) config('chatvia.governance.max_arg_bytes', 4096);
        $decoded = json_decode($raw, true);

        if (is_array($decoded)) {
            $redacted = $this->redact($decoded);
            $encoded = (string) json_encode($redacted);
            if (strlen($encoded) <= $max) {
                return [$redacted, null, false];
            }

            return [null, Utf8::truncateBytes($encoded, $max), true];
        }

        [$preview, $truncated] = $this->cap($raw === '' ? null : $raw, $max);

        return [null, $preview, $truncated];
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    private function redact(array $data): array
    {
        $keys = array_map(Str::lower(...), (array) config('chatvia.governance.redact_keys', []));

        foreach ($data as $key => $value) {
            if (collect($keys)->containsStrict(Str::lower((string) $key))) {
                $data[$key] = '[redacted]';
            } elseif (is_array($value)) {
                $data[$key] = $this->redact($value);
            }
        }

        return $data;
    }

    /**
     * @return array{0: ?string, 1: bool}
     */
    private function cap(?string $text, int $max): array
    {
        if ($text === null) {
            return [null, false];
        }
        if (strlen($text) <= $max) {
            return [$text, false];
        }

        // Cut on a character boundary so the stored text stays valid UTF-8.
        return [Utf8::truncateBytes($text, $max), true];
    }
}

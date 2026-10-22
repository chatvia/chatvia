<?php

namespace Domain\Security\Actions;

use Domain\Security\Events\SecurityEventOccurred;
use Domain\Security\Listeners\RecordSecurityEvent;
use Domain\Security\Models\SecurityAuditLog;
use Illuminate\Support\Str;
use Support\Text\Utf8;

/**
 * Writes one immutable security audit row (`security_audit_logs`) for a
 * {@see SecurityEventOccurred}. Invoked from the queued {@see RecordSecurityEvent}
 * listener, off the request path.
 *
 * Before storage it hashes the FULL raw metadata (sha256 — tamper-evidence that
 * survives redaction/truncation), then redacts configured sensitive keys and
 * size-caps the payload so the audit can never become a PII/secret sink. The
 * causer name/email are already denormalized on the event, so the row stays
 * readable after the user is deleted.
 *
 * Metadata that fits and is a JSON object or list goes in the jsonb `metadata`
 * column. Anything else (cut at the cap, or not JSON at all) goes in the text
 * `metadata_preview` column instead, because a cut JSON string is no longer an
 * array and the readers expect one.
 */
class RecordSecurityEventAction
{
    public function execute(SecurityEventOccurred $event): SecurityAuditLog
    {
        [$metadata, $preview, $truncated] = $this->redactAndCap($event->metadata);

        return SecurityAuditLog::create([
            'workspace_id' => $event->workspaceId,
            'user_id' => $event->userId,
            'user_name' => $event->userName,
            'user_email' => $event->userEmail,
            'action' => $event->action,
            'target_type' => $event->targetType,
            'target_id' => $event->targetId,
            'target_label' => $event->targetLabel,
            'ip' => $event->ip,
            'user_agent' => $event->userAgent === null ? null : Str::limit($event->userAgent, 1024, ''),
            'metadata' => $metadata,
            'metadata_preview' => $preview,
            'metadata_sha256' => hash('sha256', $event->metadata),
            'metadata_truncated' => $truncated,
            'outcome' => $event->outcome,
        ]);
    }

    /**
     * Hash-safe redaction + size cap of the raw JSON metadata. Returns the
     * redacted array when it is JSON and fits, else null plus the (possibly
     * cut) text for the preview column, and whether it was cut.
     *
     * @return array{0: array<array-key, mixed>|null, 1: ?string, 2: bool}
     */
    private function redactAndCap(string $raw): array
    {
        $max = (int) config('chatvia.security_audit.max_metadata_bytes', 8192);
        $decoded = json_decode($raw, true);

        if (is_array($decoded)) {
            $redacted = $this->redact($decoded);
            $encoded = (string) json_encode($redacted);
            if (strlen($encoded) <= $max) {
                return [$redacted, null, false];
            }

            return [null, Utf8::truncateBytes($encoded, $max), true];
        }

        if ($raw === '') {
            return [null, null, false];
        }

        if (strlen($raw) <= $max) {
            return [null, $raw, false];
        }

        // Cut on a character boundary so the stored text stays valid UTF-8.
        return [null, Utf8::truncateBytes($raw, $max), true];
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    private function redact(array $data): array
    {
        $keys = array_map(strtolower(...), (array) config('chatvia.security_audit.redact_keys', []));

        foreach ($data as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), $keys, true)) {
                $data[$key] = '[redacted]';
            } elseif (is_array($value)) {
                $data[$key] = $this->redact($value);
            }
        }

        return $data;
    }
}

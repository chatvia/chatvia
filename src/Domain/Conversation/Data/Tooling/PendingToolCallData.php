<?php

namespace Domain\Conversation\Data\Tooling;

use Domain\Integration\Tool\Enums\ToolAccessDecision;
use Domain\Integration\Tool\Enums\ToolKind;

/**
 * A tool call the tool loop is about to resolve: the model's call plus the
 * governance decision (run, ask a person, or refuse) and the display details
 * the chat UI shows for it.
 *
 * When a turn pauses for approval, every call of the turn is stored as JSON
 * on the ToolApprovalCheckpoint (`pending_calls`) and read back on resume.
 * {@see toArray()} and {@see fromArray()} keep that stored shape exactly:
 * tool_call_id, name, arguments, requires_approval, access, kind, label,
 * activity_label, is_builtin, icon_url, brand_color, reason. Older rows may
 * miss the later keys, so reading fills safe defaults.
 */
final readonly class PendingToolCallData
{
    public function __construct(
        public string $toolCallId,
        public string $name,
        public string $arguments,
        public bool $requiresApproval,
        public ?ToolAccessDecision $access,
        public ToolKind $kind,
        public ?string $label,
        public ?string $activityLabel = null,
        public bool $isBuiltin = false,
        public ?string $iconUrl = null,
        public ?string $brandColor = null,
        public ?string $reason = null,
    ) {}

    /**
     * @param  array<string, mixed>  $stored  one entry of a checkpoint's `pending_calls`
     */
    public static function fromArray(array $stored): self
    {
        return new self(
            toolCallId: (string) $stored['tool_call_id'],
            name: (string) $stored['name'],
            arguments: (string) ($stored['arguments'] ?? ''),
            requiresApproval: (bool) ($stored['requires_approval'] ?? false),
            access: ToolAccessDecision::tryFrom((string) ($stored['access'] ?? '')),
            kind: ToolKind::tryFrom((string) ($stored['kind'] ?? '')) ?? ToolKind::Function,
            label: $stored['label'] ?? null,
            activityLabel: $stored['activity_label'] ?? null,
            isBuiltin: (bool) ($stored['is_builtin'] ?? false),
            iconUrl: $stored['icon_url'] ?? null,
            brandColor: $stored['brand_color'] ?? null,
            reason: $stored['reason'] ?? null,
        );
    }

    /**
     * The stored JSON shape (see the class docblock).
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'tool_call_id' => $this->toolCallId,
            'name' => $this->name,
            'arguments' => $this->arguments,
            'requires_approval' => $this->requiresApproval,
            'access' => $this->access?->value,
            'kind' => $this->kind->value,
            'label' => $this->label,
            'activity_label' => $this->activityLabel,
            'is_builtin' => $this->isBuiltin,
            'icon_url' => $this->iconUrl,
            'brand_color' => $this->brandColor,
            'reason' => $this->reason,
        ];
    }

    /** Blocked at decision time (e.g. the per-tool rate limit), whatever a person decides. */
    public function isRateLimited(): bool
    {
        return $this->access === ToolAccessDecision::Deny;
    }
}

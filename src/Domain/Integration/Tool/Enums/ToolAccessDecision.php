<?php

namespace Domain\Integration\Tool\Enums;

/**
 * The dispatch-time governance decision for a single tool call — it subsumes the
 * old binary approval check:
 *
 *   - Allow            → the call runs inline.
 *   - RequiresApproval → the call pauses for human approval (ToolApprovalCheckpoint).
 *   - Deny             → the call is blocked (e.g. per-tool rate limit); the
 *                        runner synthesizes a recoverable result and audits it.
 *
 * Stored as its `value` on the persisted pending-call snapshot, so it round-trips
 * through a ToolApprovalCheckpoint to the resume path.
 */
enum ToolAccessDecision: string
{
    case Allow = 'allow';
    case RequiresApproval = 'requires_approval';
    case Deny = 'deny';
}

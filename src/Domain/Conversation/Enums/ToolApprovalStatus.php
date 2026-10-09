<?php

declare(strict_types=1);

namespace Domain\Conversation\Enums;

/**
 * Lifecycle of a paused tool-approval checkpoint
 * (`tool_approval_checkpoints.status`). A checkpoint is resumed at most once:
 * claiming it flips it from Pending to Resolved.
 */
enum ToolApprovalStatus: string
{
    /** Waiting for the person to approve or deny the gated tool calls. */
    case Pending = 'pending';

    /** Claimed by a resume; it can never run again. */
    case Resolved = 'resolved';

    /** Timed out before anyone decided. */
    case Expired = 'expired';
}

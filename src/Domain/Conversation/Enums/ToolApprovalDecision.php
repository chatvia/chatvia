<?php

declare(strict_types=1);

namespace Domain\Conversation\Enums;

/**
 * What the person decided for one gated tool call on the approval card. The
 * chat clients post these exact strings.
 */
enum ToolApprovalDecision: string
{
    case Approve = 'approve';
    case Deny = 'deny';
}

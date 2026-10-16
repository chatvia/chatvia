<?php

declare(strict_types=1);

namespace Domain\Security\Actions;

use Domain\Security\Data\SecurityActorData;
use Domain\Security\Enums\SecurityAuditAction;
use Domain\Security\Events\SecurityEventOccurred;
use Domain\Security\Models\BlockedVisitor;

/**
 * Removes a visitor block, so the visitor can chat again right away. The
 * removal goes to the security audit log.
 */
class UnblockVisitorAction
{
    public function execute(BlockedVisitor $block, SecurityActorData $actor): void
    {
        $block->delete();

        event(SecurityEventOccurred::for(
            SecurityAuditAction::VisitorUnblocked,
            $block->workspace_id,
            $actor,
            targetType: 'visitor',
            targetId: $block->external_id,
        ));
    }
}

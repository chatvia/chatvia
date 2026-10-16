<?php

declare(strict_types=1);

namespace Domain\Security\Actions;

use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Session;
use Domain\Security\Data\BlockVisitorData;
use Domain\Security\Enums\SecurityAuditAction;
use Domain\Security\Events\SecurityEventOccurred;
use Domain\Security\Models\BlockedVisitor;

/**
 * Blocks an anonymous visitor from every agent of the workspace, until the
 * chosen duration ends. Blocking a visitor again updates the block (new end,
 * reason and author). The browser, system and country of the attack's chat
 * session are kept so the list says who the block is about; the network
 * address is not. The block goes to the security audit log.
 */
class BlockVisitorAction
{
    public function execute(BlockVisitorData $data): BlockedVisitor
    {
        $session = $this->session($data);

        $block = BlockedVisitor::query()->updateOrCreate(
            ['workspace_id' => $data->workspaceId, 'visitor_key' => $data->visitorKey],
            [
                'agent_id' => $data->agentId,
                'browser' => $session?->browser,
                'os' => $session?->os,
                'country_id' => $session?->country_id,
                'reason' => $data->reason,
                'blocked_by_user_id' => $data->actor->id,
                'expires_at' => $data->duration->expiresAt(),
            ],
        );

        event(SecurityEventOccurred::for(
            SecurityAuditAction::VisitorBlocked,
            $data->workspaceId,
            $data->actor,
            targetType: 'visitor',
            targetId: $block->external_id,
            metadata: array_filter([
                'agent_id' => $data->agentId,
                'duration' => $data->duration->value,
                'expires_at' => $block->expires_at?->toIso8601String(),
            ]),
        ));

        return $block;
    }

    /** The chat session the attack came from, when it is still there. */
    private function session(BlockVisitorData $data): ?Session
    {
        if ($data->conversationExternalId === null) {
            return null;
        }

        return Conversation::query()
            ->where('workspace_id', $data->workspaceId)
            ->where('external_id', $data->conversationExternalId)
            ->first()
            ?->session;
    }
}

<?php

declare(strict_types=1);

namespace Domain\Conversation\Services\Tooling\Routing;

use Domain\Agent\Models\Agent;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Models\Conversation;
use Domain\Integration\Tool\Access\ToolApprovalPolicy;
use Domain\Integration\Tool\Data\ToolEntry;

/**
 * Drops the tools that need a person's approval on a surface with no Approve /
 * Deny prompt ({@see ConversationSource::showsToolApprovals()}): the widget,
 * the command bar and the messaging channels. There such a call would pause
 * the turn for good, so the model is never offered the tool and says it
 * cannot do that here instead. Voice calls ask out loud and pick their tools
 * elsewhere, so this filter is for typed turns only.
 */
class ApprovalToolFilter
{
    public function __construct(
        private readonly ToolApprovalPolicy $approvalPolicy,
    ) {}

    /**
     * @param  array<int, ToolEntry>  $entries
     * @return array<int, ToolEntry>
     */
    public function apply(Agent $agent, array $entries, ?string $conversationExternalId): array
    {
        if ($entries === [] || $conversationExternalId === null) {
            return $entries;
        }

        $surface = Conversation::query()
            ->where('external_id', $conversationExternalId)
            ->first(['source'])?->source;

        if (! $surface instanceof ConversationSource || $surface->showsToolApprovals()) {
            return $entries;
        }

        return array_values(array_filter(
            $entries,
            fn (ToolEntry $entry): bool => ! $this->approvalPolicy->requiresApproval($agent, $entry->toolName()),
        ));
    }
}

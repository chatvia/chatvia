<?php

declare(strict_types=1);

namespace Domain\Conversation\Services\Tooling\Routing;

use Domain\Agent\Models\Agent;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Services\Voice\VoiceToolCatalog;
use Domain\Integration\Tool\Access\ToolApprovalPolicy;
use Domain\Integration\Tool\Data\ToolEntry;
use Domain\Integration\Tool\Data\ToolName;

/**
 * Drops the tools that need a person's approval where nobody can approve:
 *
 *   - on a surface with no Approve / Deny prompt
 *     ({@see ConversationSource::showsToolApprovals()}): the widget, the
 *     command bar and the messaging channels. There such a call would pause
 *     the turn for good;
 *   - for an anonymous visitor on any surface ({@see withoutGated()}): a
 *     gated call runs with the workspace's own connector credentials, so
 *     only a signed-in chatter or staff account may approve one.
 *
 * The model is never offered the tool and says it cannot do that here
 * instead. Voice calls ask out loud and pick their tools elsewhere
 * ({@see VoiceToolCatalog}), so this filter is for typed turns only.
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

    /**
     * The OpenAI-shaped tool definitions without the ones that need approval,
     * for a turn with no signed-in person.
     *
     * @param  array<int, array<string, mixed>>  $definitions
     * @return array<int, array<string, mixed>>
     */
    public function withoutGated(Agent $agent, array $definitions): array
    {
        return array_values(array_filter(
            $definitions,
            fn (array $definition): bool => ! $this->approvalPolicy->requiresApproval($agent, ToolName::parse((string) ($definition['function']['name'] ?? ''))),
        ));
    }
}

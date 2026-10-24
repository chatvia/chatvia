<?php

use Domain\Conversation\Enums\ConversationSource;

/**
 * Only the hosted chat can ask a person to approve a tool call, so it is the
 * only surface that shows tool approvals.
 */
it('says only the hosted chat shows tool approvals', function (): void {
    $showing = array_values(array_filter(ConversationSource::cases(), static fn (ConversationSource $s): bool => $s->showsToolApprovals()));

    expect($showing)->toBe([ConversationSource::HostedChat]);
});

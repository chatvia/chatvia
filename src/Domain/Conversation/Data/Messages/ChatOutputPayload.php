<?php

namespace Domain\Conversation\Data\Messages;

/**
 * @deprecated Renamed to {@see AssistantTurnData}. Kept so StoreMessageJob
 *             payloads queued before the deploy still unserialize (including
 *             the old unused `sources` field). Remove after the next release.
 */
final class ChatOutputPayload extends AssistantTurnData
{
    /** @var array<mixed> */
    public array $sources = [];
}

<?php

namespace Domain\Conversation\Data\Messages;

/**
 * @deprecated Renamed to {@see UserTurnData}. Kept so StoreMessageJob payloads
 *             queued before the deploy still unserialize (including the old
 *             unused `updated_message_info` field). Remove after the next release.
 */
final class ChatInputPayload extends UserTurnData
{
    /** @var array<mixed> */
    public array $updated_message_info = [];
}

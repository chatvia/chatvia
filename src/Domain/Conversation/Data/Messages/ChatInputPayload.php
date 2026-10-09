<?php

declare(strict_types=1);

namespace Domain\Conversation\Data\Messages;

/**
 * The old class name of {@see UserTurnData}. A queued StoreMessageJob stores
 * this payload by class name, so the alias lets those jobs still unserialize,
 * together with the unused `updated_message_info` field they may carry. It can
 * be deleted once no such job is left in the queue.
 *
 * @deprecated Use {@see UserTurnData}.
 */
final class ChatInputPayload extends UserTurnData
{
    /** @var array<mixed> */
    public array $updated_message_info = [];
}

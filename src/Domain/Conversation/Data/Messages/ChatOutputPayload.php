<?php

declare(strict_types=1);

namespace Domain\Conversation\Data\Messages;

/**
 * The old class name of {@see AssistantTurnData}. A queued StoreMessageJob
 * stores this payload by class name, so the alias lets those jobs still
 * unserialize, together with the unused `sources` field they may carry. It can
 * be deleted once no such job is left in the queue.
 *
 * @deprecated Use {@see AssistantTurnData}.
 */
final class ChatOutputPayload extends AssistantTurnData
{
    /** @var array<mixed> */
    public array $sources = [];
}

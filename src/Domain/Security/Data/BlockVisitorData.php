<?php

declare(strict_types=1);

namespace Domain\Security\Data;

use Domain\Security\Enums\VisitorBlockDuration;
use Spatie\LaravelData\Data;

/**
 * A request to block an anonymous visitor from a workspace's agents: who (the
 * fingerprint), where the attack was (agent and conversation, for the list),
 * for how long, why, and the staff member doing it.
 */
class BlockVisitorData extends Data
{
    public function __construct(
        public int $workspaceId,
        public string $visitorKey,
        public VisitorBlockDuration $duration,
        public SecurityActorData $actor,
        public ?int $agentId = null,
        public ?string $conversationExternalId = null,
        public ?string $reason = null,
    ) {}
}

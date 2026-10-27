<?php

declare(strict_types=1);

namespace Domain\Conversation\Services\Handoff;

use Domain\Agent\Models\Agent;
use Support\License\Entitlements;

/**
 * Whether an agent's Chat Widget chats can be handed to a person now: the
 * install's license allows live chat (a license key; live chat is a free-use
 * limit) and the agent has it on (Setup → Widget → Live chat). New handoffs
 * and takeovers need both; a chat a person already holds finishes without
 * them, so a lapsed key never cuts off a visitor mid-chat.
 */
final readonly class HandoffAvailability
{
    public function __construct(private Entitlements $entitlements) {}

    /** Whether the install's license allows live chat. */
    public function isLicensed(): bool
    {
        return $this->entitlements->handoff();
    }

    public function isOnFor(Agent $agent): bool
    {
        return $this->isLicensed() && $agent->handoffEnabled();
    }
}

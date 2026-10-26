<?php

declare(strict_types=1);

namespace Domain\Conversation\Services\Voice;

use Domain\Agent\Models\Agent;
use Domain\Llm\Contracts\RealtimeCalls;
use Support\License\Entitlements;

/**
 * Whether voice conversations can be offered: set up on this install
 * (Settings → AI models → Voice conversations, with an OpenAI key), allowed
 * by its license (a license key; voice is a free-use limit), and turned on for
 * the agent (Setup → Settings → Composer options). The chat surfaces show the
 * voice button only when all three hold, and a call is refused otherwise.
 */
final readonly class VoiceAvailability
{
    public function __construct(
        private RealtimeCalls $calls,
        private Entitlements $entitlements,
    ) {}

    public function isSetUp(): bool
    {
        return $this->calls->isEnabled();
    }

    /** Whether the install's license allows voice conversations. */
    public function isLicensed(): bool
    {
        return $this->entitlements->voice();
    }

    public function isOnFor(Agent $agent): bool
    {
        return $this->isSetUp() && $this->isLicensed() && (bool) $agent->settings('appearance')->get('allow_voice_conversation', false);
    }
}

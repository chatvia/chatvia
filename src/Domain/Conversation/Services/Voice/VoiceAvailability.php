<?php

declare(strict_types=1);

namespace Domain\Conversation\Services\Voice;

use Domain\Agent\Models\Agent;
use Domain\Llm\Contracts\RealtimeCalls;

/**
 * Whether voice conversations can be offered: set up on this install
 * (Settings → AI models → Voice conversations, with an OpenAI key), and
 * turned on for the agent (Setup → Settings → Composer options). The chat
 * surfaces show the voice button only when both hold, and a call is refused
 * otherwise.
 */
final readonly class VoiceAvailability
{
    public function __construct(
        private RealtimeCalls $calls,
    ) {}

    public function isSetUp(): bool
    {
        return $this->calls->isEnabled();
    }

    public function isOnFor(Agent $agent): bool
    {
        return $this->isSetUp() && (bool) $agent->settings('appearance')->get('allow_voice_conversation', false);
    }
}

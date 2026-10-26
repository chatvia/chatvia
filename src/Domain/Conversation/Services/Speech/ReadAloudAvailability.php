<?php

declare(strict_types=1);

namespace Domain\Conversation\Services\Speech;

use Domain\Agent\Models\Agent;
use Domain\Llm\Contracts\Speaker;
use Domain\Llm\Data\Speech\SpeechVoiceData;

/**
 * Whether agent replies can be read aloud: set up on this install
 * (Settings → AI models → Read aloud), and turned on for the agent (Setup →
 * Settings → Composer options). The hosted chat shows the speaker button
 * only when both hold, and a request is refused otherwise. Also the voice
 * an agent reads with.
 */
final readonly class ReadAloudAvailability
{
    /** The agent setting that means "the admin's default voice". */
    public const string DEFAULT_VOICE = 'default';

    public function __construct(
        private Speaker $speaker,
    ) {}

    public function isSetUp(): bool
    {
        return $this->speaker->isEnabled();
    }

    public function isOnFor(Agent $agent): bool
    {
        return $this->isSetUp() && (bool) $agent->settings('appearance')->get('allow_read_aloud', false);
    }

    /**
     * The agent's own voice while the service in use has it (a voice of
     * another service, or one removed from the ElevenLabs account, does
     * not), else the admin's default. $speaker is another speech service to
     * pick the voice of, such as the one that makes audio files.
     */
    public function voiceFor(Agent $agent, ?Speaker $speaker = null): string
    {
        $speaker ??= $this->speaker;
        $picked = (string) $agent->settings('appearance')->get('read_aloud_voice', self::DEFAULT_VOICE);

        if ($picked !== self::DEFAULT_VOICE && array_any($speaker->voices(), fn (SpeechVoiceData $voice): bool => $voice->id === $picked)) {
            return $picked;
        }

        return $speaker->defaultVoice();
    }
}

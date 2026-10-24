<?php

declare(strict_types=1);

namespace Domain\Llm\Enums;

use Illuminate\Support\Str;

/**
 * A voice an agent can speak with in a voice conversation: the built-in
 * voices of the OpenAI Realtime API. OpenAI recommends Marin and Cedar for
 * the best quality. The admin picks the default (Settings → AI models), and
 * an agent may pick its own (Setup → Settings → Composer options).
 */
enum RealtimeVoice: string
{
    case Marin = 'marin';
    case Cedar = 'cedar';
    case Alloy = 'alloy';
    case Ash = 'ash';
    case Ballad = 'ballad';
    case Coral = 'coral';
    case Echo = 'echo';
    case Sage = 'sage';
    case Shimmer = 'shimmer';
    case Verse = 'verse';

    /** The agent setting that means "the admin's default voice". */
    public const string DEFAULT = 'default';

    public function label(): string
    {
        return Str::ucfirst($this->value);
    }

    /**
     * The values an agent's `voice` setting may hold: the default, then each voice.
     *
     * @return list<string>
     */
    public static function agentChoices(): array
    {
        return [self::DEFAULT, ...array_column(self::cases(), 'value')];
    }

    /** The configured default voice, or Marin when the setting holds something else. */
    public static function configured(): self
    {
        return self::tryFrom((string) config('services.openai.realtime_voice')) ?? self::Marin;
    }

    /** An agent's voice setting, resolved: its own voice, else the default. */
    public static function forSetting(mixed $value): self
    {
        return filled($value) ? self::tryFrom($value) ?? self::configured() : self::configured();
    }
}

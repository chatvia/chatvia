<?php

declare(strict_types=1);

namespace Domain\Llm\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * How hard an agent's model thinks before it answers. An agent picks one
 * (Setup → AI model) while the admin lets agents reason (Settings → AI
 * models); otherwise every agent is Off. Each provider sends it in its own
 * words, and Off as the least thinking its models accept: some reasoning
 * models cannot stop thinking, but their thinking is then never shown.
 * The values are stored, so never rename one.
 */
#[TypeScript]
enum ReasoningEffort: string
{
    case Off = 'off';
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';

    public function isOff(): bool
    {
        return $this === self::Off;
    }
}

<?php

declare(strict_types=1);

namespace Domain\Agent\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Where an agent's custom domain stands, as the Deploy page shows it:
 * saved and not checked yet, serving the chat, or failing its checks.
 * Worked out from the row, never stored.
 */
#[TypeScript]
enum AgentDomainStatus: string
{
    case Waiting = 'waiting';
    case Active = 'active';
    case Problem = 'problem';
}

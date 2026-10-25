<?php

declare(strict_types=1);

namespace Domain\Agent\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * How an agent's answers show the knowledge they used (Setup → Sources):
 * not at all (the sources never leave the server), as a list under the
 * answer, or as numbered `[n]` citations in the text with a numbered list.
 * The value is stored in the agent's settings, so never rename one.
 */
#[TypeScript]
enum SourcesDisplay: string
{
    case Hidden = 'hidden';
    case List = 'list';
    case Citations = 'citations';
}

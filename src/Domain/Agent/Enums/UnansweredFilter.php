<?php

declare(strict_types=1);

namespace Domain\Agent\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Which replies the "Unanswered" list shows: every reply that needs a look,
 * only those for which no knowledge was found, or only those a visitor gave
 * a thumbs-down.
 */
#[TypeScript]
enum UnansweredFilter: string
{
    case All = 'all';
    case NotFound = 'not_found';
    case ThumbsDown = 'thumbs_down';
}

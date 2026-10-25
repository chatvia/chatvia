<?php

declare(strict_types=1);

namespace Domain\Conversation\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The chat log's "Unanswered" filter: conversations with a reply that needs
 * a look (no knowledge found or a thumbs-down), or only those with a reply
 * for which no knowledge was found. A thumbs-down alone is the feedback
 * filter's job.
 */
#[TypeScript]
enum UnansweredFilter: string
{
    case NeedsLook = 'needs_look';
    case NotFound = 'not_found';
}

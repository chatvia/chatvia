<?php

declare(strict_types=1);

namespace Domain\Guardrail\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Who a guardrail trigger can be blamed on: a signed-in chatter (blocked by
 * switching their account off) or an anonymous visitor (blocked by their
 * fingerprint).
 */
#[TypeScript]
enum GuardrailOffenderKind: string
{
    case Chatter = 'chatter';
    case Visitor = 'visitor';
}

<?php

declare(strict_types=1);

namespace Domain\Security\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * How an audited security operation ended (`security_audit_log.outcome`):
 * it worked, it failed (a wrong password, a provider error), or it was
 * refused by a policy or a permission check. Stored, so the values must
 * not change.
 */
#[TypeScript]
enum SecurityAuditOutcome: string
{
    case Success = 'success';
    case Failure = 'failure';
    case Denied = 'denied';
}

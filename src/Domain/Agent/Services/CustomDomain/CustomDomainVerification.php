<?php

declare(strict_types=1);

namespace Domain\Agent\Services\CustomDomain;

use Illuminate\Support\Str;

/**
 * How a workspace proves it owns a custom domain: a TXT record named
 * `_chatvia.<domain>` holding the agent's verification token. Until the
 * record is found the domain stays pending and never shows the chat.
 */
final class CustomDomainVerification
{
    private const string RECORD_PREFIX = '_chatvia';

    /** The name of the TXT record that proves the domain. */
    public static function recordName(string $domain): string
    {
        return self::RECORD_PREFIX.'.'.$domain;
    }

    /** A new verification token for an agent. */
    public static function newToken(): string
    {
        return 'chatvia-verify-'.Str::lower(Str::random(32));
    }
}

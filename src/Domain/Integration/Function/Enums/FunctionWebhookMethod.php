<?php

declare(strict_types=1);

namespace Domain\Integration\Function\Enums;

/**
 * HTTP method Chatvia uses when calling a function's webhook. The values are
 * stored in `functions.webhook_method` and shown in the public API.
 */
enum FunctionWebhookMethod: string
{
    case Post = 'POST';
    case Get = 'GET';
    case Patch = 'PATCH';
    case Delete = 'DELETE';

    /**
     * True for every verb that may change data on the receiving side. Such
     * calls ask a person for approval by default and count as write tools.
     */
    public function changesData(): bool
    {
        return $this !== self::Get;
    }
}

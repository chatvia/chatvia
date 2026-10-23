<?php

declare(strict_types=1);

namespace App\Http\Resources\Inbox;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * How many Inbox chats wait for a human: the badge on the Inbox link.
 */
#[TypeScript]
class InboxWaitingCountData extends Data
{
    public function __construct(
        public int $count,
    ) {}
}

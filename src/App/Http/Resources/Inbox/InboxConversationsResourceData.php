<?php

declare(strict_types=1);

namespace App\Http\Resources\Inbox;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The Inbox list and how many of its chats wait for a human.
 */
#[TypeScript]
class InboxConversationsResourceData extends Data
{
    public function __construct(
        /** @var DataCollection<int, InboxConversationData> */
        #[DataCollectionOf(InboxConversationData::class)]
        public DataCollection $conversations,
        public int $waiting_count,
    ) {}
}

<?php

namespace Domain\Channel\Messenger\Data;

use Domain\Channel\Enums\MetaApp;
use Domain\Channel\Messenger\Models\AgentMessengerPage;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The Messenger card of the agent's Channels page. Messenger connects via
 * Facebook OAuth (one Chatvia Meta app), so there's no per-page webhook URL.
 * `available` is false until an admin sets up the Messenger Meta app; `pick`
 * holds the Pages returned from Facebook awaiting the workspace's choice.
 */
#[TypeScript]
class MessengerChannelCardData extends Data
{
    public function __construct(
        public bool $available,
        public bool $connected,
        public ?string $page_name,
        public bool $enabled,
        /** @var DataCollection<int, MessengerPagePickData>|null */
        #[DataCollectionOf(MessengerPagePickData::class)]
        public ?DataCollection $pick,
    ) {}

    /**
     * @param  list<array<string, string>>  $pending
     */
    public static function fromPage(?AgentMessengerPage $page, array $pending): self
    {
        return new self(
            available: MetaApp::Messenger->isConfigured(),
            connected: $page !== null,
            page_name: $page?->page_name,
            enabled: (bool) $page?->is_enabled,
            pick: $pending === [] ? null : new DataCollection(MessengerPagePickData::class, array_map(MessengerPagePickData::fromArray(...), $pending)),
        );
    }
}

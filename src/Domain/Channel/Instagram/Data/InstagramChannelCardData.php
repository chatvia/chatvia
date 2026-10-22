<?php

namespace Domain\Channel\Instagram\Data;

use Domain\Channel\Enums\MetaApp;
use Domain\Channel\Instagram\Models\AgentInstagramAccount;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The Instagram card of the agent's Channels page. Instagram connects via
 * Facebook OAuth (one Chatvia Meta app) — no per-account webhook URL.
 * `available` is false until an admin sets up the Instagram Meta app; `pick`
 * holds the IG-linked accounts returned from Facebook awaiting the
 * workspace's choice.
 */
#[TypeScript]
class InstagramChannelCardData extends Data
{
    public function __construct(
        public bool $available,
        public bool $connected,
        public ?string $username,
        public bool $enabled,
        /** @var DataCollection<int, InstagramAccountPickData>|null */
        #[DataCollectionOf(InstagramAccountPickData::class)]
        public ?DataCollection $pick,
    ) {}

    /**
     * @param  list<array<string, string>>  $pending
     */
    public static function fromAccount(?AgentInstagramAccount $account, array $pending): self
    {
        return new self(
            available: MetaApp::Instagram->isConfigured(),
            connected: $account instanceof AgentInstagramAccount,
            username: $account?->username,
            enabled: (bool) $account?->is_enabled,
            pick: $pending === [] ? null : new DataCollection(InstagramAccountPickData::class, array_map(InstagramAccountPickData::fromArray(...), $pending)),
        );
    }
}

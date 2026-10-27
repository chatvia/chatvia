<?php

declare(strict_types=1);

namespace Domain\Channel\Actions;

use Domain\Channel\Data\MetaPageData;
use Domain\Channel\Enums\MetaOAuthChannel;
use Domain\Channel\Instagram\Data\InstagramAccountData;
use Domain\Channel\Services\MetaOAuthClient;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Finishes a Facebook Login for Business round-trip for Messenger or
 * Instagram: swap the returned code for a long-lived user token, then list what
 * the user granted — the Pages (Messenger) or the Page-linked Instagram
 * professional accounts (Instagram), each with its own page token. The
 * workspace then picks one; nothing is stored here. An empty list means the
 * exchange failed or nothing usable was granted.
 */
class CompleteMetaOAuthAction
{
    public function __construct(
        private readonly MetaOAuthClient $oauth,
    ) {}

    /**
     * @return Collection<int, MetaPageData|InstagramAccountData>
     */
    public function execute(MetaOAuthChannel $channel, string $code): Collection
    {
        $userToken = $this->oauth->exchangeCodeForUserToken($channel->app(), $code);

        $accounts = collect(match (true) {
            $userToken === null => [],
            $channel === MetaOAuthChannel::Messenger => $this->oauth->pages($userToken),
            default => $this->oauth->pagesWithInstagram($userToken),
        });

        if ($accounts->isEmpty()) {
            Log::warning("{$channel->label()} OAuth callback: nothing usable was granted", [
                'got_user_token' => $userToken !== null,
            ]);
        }

        return $accounts;
    }
}

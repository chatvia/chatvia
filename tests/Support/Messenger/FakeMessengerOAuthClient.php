<?php

declare(strict_types=1);

namespace Tests\Support\Messenger;

use Domain\Channel\Data\MetaPageData;
use Domain\Channel\Enums\MetaApp;
use Domain\Channel\Services\MetaOAuthClient;

/**
 * Stand-in for {@see MetaOAuthClient} in the Messenger connect flow: hands back
 * a fixed user token (`$userToken = null` fakes a failed code exchange) and the
 * Pages in `$pagesList`, so tests never call Facebook.
 */
class FakeMessengerOAuthClient extends MetaOAuthClient
{
    /** @var list<array{id: string, name: string, access_token: string}> */
    public array $pagesList = [['id' => 'PAGE-1', 'name' => 'Acme Support', 'access_token' => 'EAAG-page-token']];

    public ?string $userToken = 'USER-LONG-TOKEN';

    public function authorizeUrl(MetaApp $app, string $state, string $scopes): string
    {
        return 'https://www.facebook.com/dialog/oauth?state='.$state;
    }

    public function exchangeCodeForUserToken(MetaApp $app, string $code): ?string
    {
        return $this->userToken;
    }

    public function pages(string $userToken): array
    {
        return array_map(MetaPageData::from(...), $this->pagesList);
    }
}

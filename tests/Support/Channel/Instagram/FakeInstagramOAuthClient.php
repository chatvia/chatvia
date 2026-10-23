<?php

declare(strict_types=1);

namespace Tests\Support\Channel\Instagram;

use Domain\Channel\Enums\MetaApp;
use Domain\Channel\Instagram\Data\InstagramAccountData;
use Domain\Channel\Services\MetaOAuthClient;

/**
 * Stand-in for {@see MetaOAuthClient} in the Instagram connect flow: hands back
 * a fixed user token (`$userToken = null` fakes a failed code exchange) and the
 * Page-linked accounts in `$accountsList`, so tests never call Facebook.
 */
class FakeInstagramOAuthClient extends MetaOAuthClient
{
    /** @var list<array{page_id: string, page_name: string, page_access_token: string, ig_id: string, ig_username: string}> */
    public array $accountsList = [[
        'page_id' => '998877',
        'page_name' => 'Acme Support',
        'page_access_token' => 'EAAG-page-token',
        'ig_id' => '17840012345',
        'ig_username' => 'acme.support',
    ]];

    public ?string $userToken = 'USER-LONG-TOKEN';

    public function authorizeUrl(MetaApp $app, string $state, string $scopes): string
    {
        return 'https://www.facebook.com/dialog/oauth?state='.$state;
    }

    public function exchangeCodeForUserToken(MetaApp $app, string $code): ?string
    {
        return $this->userToken;
    }

    public function pagesWithInstagram(string $userToken): array
    {
        return array_map(InstagramAccountData::from(...), $this->accountsList);
    }
}

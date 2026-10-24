<?php

declare(strict_types=1);

namespace Tests\Support\Channel;

use Domain\Channel\Data\MetaPageData;
use Domain\Channel\Enums\MetaApp;
use Domain\Channel\Instagram\Data\InstagramAccountData;
use Domain\Channel\Services\MetaOAuthClient;

/**
 * Stand-in for {@see MetaOAuthClient} in every Meta connect flow, so tests
 * never call Facebook. Facebook Login codes trade for `$userToken` and list
 * `$pages` (Messenger) or `$instagramAccounts` (Instagram); an Embedded Signup
 * code (WhatsApp) trades for `$businessToken`. A null token fakes a rejected
 * code. `$exchangedOn` records the callback URL of the app a code was
 * exchanged on.
 */
class FakeMetaOAuthClient extends MetaOAuthClient
{
    public ?string $userToken = 'USER-LONG-TOKEN';

    public ?string $businessToken = 'EAAG-business-token';

    public ?string $exchangedOn = null;

    /** @var list<MetaPageData> */
    public array $pages;

    /** @var list<InstagramAccountData> */
    public array $instagramAccounts;

    public function __construct()
    {
        $this->pages = [new MetaPageData('PAGE-1', 'Acme Support', 'EAAG-page-token')];
        $this->instagramAccounts = [new InstagramAccountData('998877', 'Acme Support', 'EAAG-page-token', '17840012345', 'acme.support')];
    }

    public function authorizeUrl(MetaApp $app, string $state, string $scopes): string
    {
        return 'https://www.facebook.com/dialog/oauth?state='.$state;
    }

    public function exchangeCodeForUserToken(MetaApp $app, string $code): ?string
    {
        $this->exchangedOn = $app->redirectUri();

        return $this->userToken;
    }

    public function exchangeEmbeddedSignupCode(string $code): ?string
    {
        return $this->businessToken;
    }

    public function pages(string $userToken): array
    {
        return $this->pages;
    }

    public function pagesWithInstagram(string $userToken): array
    {
        return $this->instagramAccounts;
    }
}

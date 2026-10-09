<?php

use Domain\Channel\Actions\CompleteMetaOAuthAction;
use Domain\Channel\Data\MetaPageData;
use Domain\Channel\Enums\MetaApp;
use Domain\Channel\Enums\MetaOAuthChannel;
use Domain\Channel\Instagram\Data\InstagramAccountData;
use Domain\Channel\Services\MetaOAuthClient;

/**
 * Finishing the Facebook Login round-trip lists what the user granted: Pages
 * for Messenger, Page-linked Instagram accounts for Instagram — exchanging the
 * code with the channel's own callback URL.
 */
function fakeCompleteOAuth(?string $userToken): MetaOAuthClient
{
    return new class($userToken) extends MetaOAuthClient
    {
        public ?string $exchangedWith = null;

        public function __construct(private readonly ?string $userToken) {}

        public function exchangeCodeForUserToken(MetaApp $app, string $code): ?string
        {
            $this->exchangedWith = $app->redirectUri();

            return $this->userToken;
        }

        public function pages(string $userToken): array
        {
            return [new MetaPageData('PAGE-1', 'Acme', 'EAAG-page')];
        }

        public function pagesWithInstagram(string $userToken): array
        {
            return [new InstagramAccountData('PAGE-1', 'Acme', 'EAAG-page', 'IG-1', 'acme')];
        }
    };
}

it('lists the granted Pages for Messenger', function (): void {
    $accounts = new CompleteMetaOAuthAction(fakeCompleteOAuth('USER'))->execute(MetaOAuthChannel::Messenger, 'code');

    expect($accounts)->toHaveCount(1)
        ->and($accounts[0])->toBeInstanceOf(MetaPageData::class);
});

it('lists the IG accounts for Instagram, exchanging with the Instagram callback URL', function (): void {
    config(['services.meta.instagram.redirect' => 'https://app.example/ig/callback']);
    $oauth = fakeCompleteOAuth('USER');

    $accounts = new CompleteMetaOAuthAction($oauth)->execute(MetaOAuthChannel::Instagram, 'code');

    expect($accounts[0])->toBeInstanceOf(InstagramAccountData::class)
        ->and($oauth->exchangedWith)->toBe('https://app.example/ig/callback');
});

it('returns nothing when the code exchange fails', function (): void {
    expect(new CompleteMetaOAuthAction(fakeCompleteOAuth(null))->execute(MetaOAuthChannel::Messenger, 'code'))->toBe([]);
});

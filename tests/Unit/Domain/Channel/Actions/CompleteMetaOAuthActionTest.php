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
 * code with the channel's own callback URL. The fake records the callback URL
 * of the app it was asked to exchange the code with.
 */
function completeMetaOAuthClient(?string $userToken): MetaOAuthClient
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

it('exchanges the code on the channel’s own callback URL and lists what was granted', function (MetaOAuthChannel $channel, string $callback, Closure $granted): void {
    config([
        'services.meta.messenger.redirect' => 'https://app.example/messenger/callback',
        'services.meta.instagram.redirect' => 'https://app.example/instagram/callback',
    ]);
    $oauth = completeMetaOAuthClient('USER');

    $accounts = new CompleteMetaOAuthAction($oauth)->execute($channel, 'code');

    expect($oauth->exchangedWith)->toBe($callback)
        ->and($accounts)->toEqual($granted());
})->with([
    'Messenger lists the Pages' => [
        MetaOAuthChannel::Messenger,
        'https://app.example/messenger/callback',
        fn (): array => [new MetaPageData('PAGE-1', 'Acme', 'EAAG-page')],
    ],
    'Instagram lists the Page-linked accounts' => [
        MetaOAuthChannel::Instagram,
        'https://app.example/instagram/callback',
        fn (): array => [new InstagramAccountData('PAGE-1', 'Acme', 'EAAG-page', 'IG-1', 'acme')],
    ],
]);

it('returns nothing when the code exchange fails', function (): void {
    expect(new CompleteMetaOAuthAction(completeMetaOAuthClient(null))->execute(MetaOAuthChannel::Messenger, 'code'))->toBe([]);
});

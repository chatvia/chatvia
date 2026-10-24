<?php

use Domain\Channel\Actions\CompleteMetaOAuthAction;
use Domain\Channel\Data\MetaPageData;
use Domain\Channel\Enums\MetaOAuthChannel;
use Domain\Channel\Instagram\Data\InstagramAccountData;
use Tests\Support\Channel\FakeMetaOAuthClient;

/**
 * Finishing the Facebook Login round-trip lists what the user granted: Pages
 * for Messenger, Page-linked Instagram accounts for Instagram, exchanging the
 * code on the channel's own callback URL.
 */
it('exchanges the code on the channel’s own callback URL and lists what was granted', function (MetaOAuthChannel $channel, string $callback, Closure $granted): void {
    config([
        'services.meta.messenger.redirect' => 'https://app.example/messenger/callback',
        'services.meta.instagram.redirect' => 'https://app.example/instagram/callback',
    ]);
    $oauth = new FakeMetaOAuthClient;

    $accounts = new CompleteMetaOAuthAction($oauth)->execute($channel, 'code');

    expect($oauth->exchangedOn)->toBe($callback)
        ->and($accounts)->toEqual($granted());
})->with([
    'Messenger lists the Pages' => [
        MetaOAuthChannel::Messenger,
        'https://app.example/messenger/callback',
        fn (): array => [new MetaPageData('PAGE-1', 'Acme Support', 'EAAG-page-token')],
    ],
    'Instagram lists the Page-linked accounts' => [
        MetaOAuthChannel::Instagram,
        'https://app.example/instagram/callback',
        fn (): array => [new InstagramAccountData('998877', 'Acme Support', 'EAAG-page-token', '17840012345', 'acme.support')],
    ],
]);

it('returns nothing when the code exchange fails', function (): void {
    $oauth = new FakeMetaOAuthClient;
    $oauth->userToken = null;

    expect(new CompleteMetaOAuthAction($oauth)->execute(MetaOAuthChannel::Messenger, 'code'))->toBe([]);
});

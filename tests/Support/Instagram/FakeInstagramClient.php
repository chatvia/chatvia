<?php

namespace Tests\Support\Instagram;

use Domain\Channel\Instagram\Services\InstagramClient;

/**
 * In-memory stand-in for {@see InstagramClient} so tests never touch the Meta
 * Graph API. Records outbound sends/typing/subscriptions. Under Path B the
 * token is the linked Facebook Page token.
 */
class FakeInstagramClient extends InstagramClient
{
    /** @var array{name: ?string, username: ?string}|null */
    public ?array $profile = ['name' => 'Sam', 'username' => 'sam.codes'];

    /** @var list<array{pageToken: string, pageId: string}> */
    public array $subscriptions = [];

    /** @var list<array{token: string, igsid: string, markdown: string}> */
    public array $sent = [];

    /** @var list<array{token: string, igsid: string}> */
    public array $typing = [];

    public function subscribeAccount(string $pageToken, string $pageId): bool
    {
        $this->subscriptions[] = compact('pageToken', 'pageId');

        return true;
    }

    public function userProfile(string $pageToken, string $igsid): ?array
    {
        return $this->profile;
    }

    public function sendMessage(string $pageToken, string $igsid, string $markdown): void
    {
        $this->sent[] = ['token' => $pageToken, 'igsid' => $igsid, 'markdown' => $markdown];
    }

    public function sendTypingAction(string $pageToken, string $igsid): void
    {
        $this->typing[] = ['token' => $pageToken, 'igsid' => $igsid];
    }
}

<?php

namespace Tests\Support\Instagram;

use Domain\Channel\Instagram\Services\InstagramClient;

/**
 * In-memory stand-in for {@see InstagramClient} so tests never touch the Meta
 * Graph API. Records outbound sends/typing/subscriptions and lets a test
 * pretend a token is valid (`$account`) or rejected (`$account = null`).
 */
class FakeInstagramClient extends InstagramClient
{
    /** @var array{id: ?string, username: ?string}|null */
    public ?array $account = ['id' => '17840012345', 'username' => 'acme.support'];

    /** @var array{name: ?string, username: ?string}|null */
    public ?array $profile = ['name' => 'Sam', 'username' => 'sam.codes'];

    /** @var list<string> */
    public array $subscriptions = [];

    /** @var list<array{token: string, igsid: string, markdown: string}> */
    public array $sent = [];

    /** @var list<array{token: string, igsid: string}> */
    public array $typing = [];

    public function accountInfo(string $token): ?array
    {
        return $this->account;
    }

    public function subscribeAccount(string $token): bool
    {
        $this->subscriptions[] = $token;

        return true;
    }

    public function userProfile(string $token, string $igsid): ?array
    {
        return $this->profile;
    }

    public function sendMessage(string $token, string $igsid, string $markdown): void
    {
        $this->sent[] = compact('token', 'igsid', 'markdown');
    }

    public function sendTypingAction(string $token, string $igsid): void
    {
        $this->typing[] = compact('token', 'igsid');
    }
}

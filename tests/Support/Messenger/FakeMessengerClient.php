<?php

namespace Tests\Support\Messenger;

use Domain\Channel\Messenger\Services\MessengerClient;

/**
 * In-memory stand-in for {@see MessengerClient} so tests never touch the Meta
 * Graph API. Records outbound sends/typing/subscriptions and lets a test
 * pretend a token is valid (`$page`) or rejected (`$page = null`).
 */
class FakeMessengerClient extends MessengerClient
{
    /** @var array{id: ?string, name: ?string}|null */
    public ?array $page = ['id' => '1234567890', 'name' => 'Acme Support'];

    /** @var array{first_name: ?string}|null */
    public ?array $profile = ['first_name' => 'Sam'];

    /** @var list<array{token: string, pageId: string}> */
    public array $subscriptions = [];

    /** @var list<array{token: string, psid: string, markdown: string}> */
    public array $sent = [];

    /** @var list<array{token: string, psid: string}> */
    public array $typing = [];

    public function pageInfo(string $token): ?array
    {
        return $this->page;
    }

    public function subscribePage(string $token, string $pageId): bool
    {
        $this->subscriptions[] = compact('token', 'pageId');

        return true;
    }

    public function userProfile(string $token, string $psid): ?array
    {
        return $this->profile;
    }

    public function sendMessage(string $token, string $psid, string $markdown): void
    {
        $this->sent[] = compact('token', 'psid', 'markdown');
    }

    public function sendTypingAction(string $token, string $psid): void
    {
        $this->typing[] = compact('token', 'psid');
    }
}

<?php

declare(strict_types=1);

namespace Tests\Support\Channel\Slack;

use Domain\Channel\Slack\Services\SlackClient;

/**
 * In-memory stand-in for {@see SlackClient} so tests never touch the Slack Web
 * API. Records outbound posts/updates and lets a test pretend a token is valid
 * (`$identity`) or rejected (`$identity = null`), or that posting fails
 * (`$nextTs = null`).
 */
class FakeSlackClient extends SlackClient
{
    /** @var array{team: ?string, user_id: ?string}|null */
    public ?array $identity = ['team' => 'Acme Inc', 'user_id' => 'U0BOT'];

    /** @var array{name: ?string, is_im: bool}|null */
    public ?array $channel = ['name' => 'support', 'is_im' => false];

    /** The ts returned by postMessage; null simulates a failed post. */
    public ?string $nextTs = '1700000000.000100';

    /** @var list<array{token: string, channelId: string, markdown: string, threadTs: ?string}> */
    public array $posted = [];

    /** @var list<array{token: string, channelId: string, ts: string, markdown: string}> */
    public array $updates = [];

    public function authTest(string $token): ?array
    {
        return $this->identity;
    }

    public function channelInfo(string $token, string $channelId): ?array
    {
        return $this->channel;
    }

    public function postMessage(string $token, string $channelId, string $markdown, ?string $threadTs = null): ?string
    {
        $this->posted[] = ['token' => $token, 'channelId' => $channelId, 'markdown' => $markdown, 'threadTs' => $threadTs];

        return $this->nextTs;
    }

    public function updateMessage(string $token, string $channelId, string $ts, string $markdown): bool
    {
        $this->updates[] = ['token' => $token, 'channelId' => $channelId, 'ts' => $ts, 'markdown' => $markdown];

        return true;
    }
}

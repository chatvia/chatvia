<?php

declare(strict_types=1);

namespace Tests\Support\Channel\Teams;

use Domain\Channel\Teams\Models\AgentTeamsBot;
use Domain\Channel\Teams\Services\TeamsClient;

/**
 * In-memory stand-in for {@see TeamsClient} so tests never touch Entra or the
 * Bot Framework connector. Records outbound posts/updates and lets a test
 * pretend the credentials are valid (`$credentialsValid`) or that posting
 * fails (`$nextActivityId = null`).
 */
class FakeTeamsClient extends TeamsClient
{
    public bool $credentialsValid = true;

    /** The activity id returned by postMessage; null simulates a failed post. */
    public ?string $nextActivityId = 'act-0001';

    /** @var list<array{serviceUrl: string, conversationId: string, markdown: string}> */
    public array $posted = [];

    /** @var list<array{serviceUrl: string, conversationId: string, activityId: string, markdown: string}> */
    public array $updates = [];

    public function validateCredentials(string $appId, string $appSecret, ?string $tenantId): bool
    {
        return $this->credentialsValid;
    }

    public function postMessage(AgentTeamsBot $bot, string $serviceUrl, string $conversationId, string $markdown): ?string
    {
        $this->posted[] = ['serviceUrl' => $serviceUrl, 'conversationId' => $conversationId, 'markdown' => $markdown];

        return $this->nextActivityId;
    }

    public function updateMessage(AgentTeamsBot $bot, string $serviceUrl, string $conversationId, string $activityId, string $markdown): bool
    {
        $this->updates[] = ['serviceUrl' => $serviceUrl, 'conversationId' => $conversationId, 'activityId' => $activityId, 'markdown' => $markdown];

        return true;
    }
}

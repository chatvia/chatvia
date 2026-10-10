<?php

declare(strict_types=1);

namespace App\Identity\Workspace\Listeners;

use App\Identity\Workspace\Actions\MessageLimits\RecordMessageUsageAction;
use Domain\Conversation\Events\AssistantReplyCompleted;

/**
 * Counts every completed agent reply against the workspace's monthly message
 * limit. A guardrail refusal sends no {@see AssistantReplyCompleted}, so it is
 * not counted.
 */
class CountReplyAgainstMessageLimit
{
    public function __construct(
        private readonly RecordMessageUsageAction $recordUsage,
    ) {}

    public function handle(AssistantReplyCompleted $event): void
    {
        $this->recordUsage->execute($event->workspaceId);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Support\Conversation;

use Domain\Conversation\Services\ChatContext;
use Domain\Conversation\Services\Turn\AssistantReplyRecorder;

/**
 * An {@see AssistantReplyRecorder} that stores nothing and remembers the text
 * of every answer and refusal it was asked to save, so a test of a streaming
 * source can check what would have been saved.
 */
final class RecordingReplyRecorder extends AssistantReplyRecorder
{
    /** @var list<string> */
    public array $saved = [];

    /** @var list<string> */
    public array $refusals = [];

    public function __construct()
    {
        // No parent constructor: nothing is stored.
    }

    public function recordCompletedReply(ChatContext $context): void
    {
        $this->saved[] = $context->streamedMessage;
    }

    public function recordRefusal(ChatContext $context): void
    {
        $this->refusals[] = (string) $context->guardRefusal;
    }
}

<?php

declare(strict_types=1);

namespace Domain\Analytics\Listeners;

use Domain\Analytics\Events\MessageSent;
use Domain\Analytics\Jobs\AnalyzeMessageJob;
use Domain\Conversation\Enums\MessageRole;

/**
 * Queues sentiment and tone analysis for every stored visitor message. AI
 * replies are skipped: their text is the model's, not the visitor's. Runs on
 * the request (it only queues the job), right after the message is committed.
 */
class QueueMessageAnalysis
{
    public function handle(MessageSent $event): void
    {
        AnalyzeMessageJob::dispatchIf($event->role === MessageRole::User->value, $event->messageExternalId);
    }
}

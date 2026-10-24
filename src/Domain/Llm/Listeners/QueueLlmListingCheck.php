<?php

declare(strict_types=1);

namespace Domain\Llm\Listeners;

use Domain\Llm\Events\LlmCallRefused;
use Domain\Llm\Jobs\CheckLlmListingJob;

/**
 * Checks the provider's model list off the request after it refused a call
 * to a model. The job is unique per model for a while, so a burst of
 * refused calls reads the list once.
 */
class QueueLlmListingCheck
{
    public function handle(LlmCallRefused $event): void
    {
        CheckLlmListingJob::dispatch($event->llmId);
    }
}

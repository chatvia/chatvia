<?php

declare(strict_types=1);

namespace Domain\Llm\Events;

use Domain\Llm\Listeners\QueueLlmListingCheck;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A provider answered a call to one of the catalog's models as if it does not
 * serve that model (HTTP 404), so {@see QueueLlmListingCheck} asks the
 * provider's list whether it still does.
 */
final readonly class LlmCallRefused
{
    use Dispatchable;

    public function __construct(
        public int $llmId,
    ) {}
}

<?php

declare(strict_types=1);

namespace Domain\Llm\Events;

use Domain\Llm\Actions\Catalog\CheckLlmListingAction;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * {@see CheckLlmListingAction} found that a provider no longer serves one of
 * the catalog's models, so the model is no longer offered and the app's own
 * tasks that used it fall back to another one.
 */
final class LlmUnlisted
{
    use Dispatchable;

    public function __construct(
        public readonly int $llmId,
    ) {}
}

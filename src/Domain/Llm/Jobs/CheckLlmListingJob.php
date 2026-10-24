<?php

declare(strict_types=1);

namespace Domain\Llm\Jobs;

use Domain\Llm\Actions\Catalog\CheckLlmListingAction;
use Domain\Llm\Models\Llm;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\Attributes\UniqueFor;

/**
 * Asks a model's provider whether it still serves the model, after the
 * provider refused a call to it ({@see CheckLlmListingAction}).
 */
// A failed list read changes nothing; the next refused call tries again.
#[Tries(1)]
// Every refused call asks again, so one read per model covers a burst.
#[UniqueFor(600)]
class CheckLlmListingJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $llmId,
    ) {}

    public function uniqueId(): string
    {
        return (string) $this->llmId;
    }

    public function handle(CheckLlmListingAction $action): void
    {
        $llm = Llm::query()->with('provider')->find($this->llmId);

        if ($llm instanceof Llm) {
            $action->execute($llm->provider, $llm);
        }
    }
}

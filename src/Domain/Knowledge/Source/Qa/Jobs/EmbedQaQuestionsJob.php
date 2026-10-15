<?php

declare(strict_types=1);

namespace Domain\Knowledge\Source\Qa\Jobs;

use Domain\Knowledge\Source\Qa\Actions\EmbedQaQuestionsAction;
use Domain\Knowledge\Source\Qa\Models\AgentQuestionAnswer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\DeleteWhenMissingModels;
use Illuminate\Queue\Attributes\Queue;
use Illuminate\Queue\Attributes\Tries;

/**
 * Embeds the questions of one curated Q&A entry, off the request path.
 */
#[Queue('ingestion.chunks')]
// An embedding call that fails once often works on the next try.
#[Tries(3)]
// Longer waits, so a busy embedding service can catch up.
#[Backoff(10, 60)]
// An entry deleted before the job runs needs no vectors.
#[DeleteWhenMissingModels]
class EmbedQaQuestionsJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public AgentQuestionAnswer $qa,
    ) {}

    public function handle(EmbedQaQuestionsAction $action): void
    {
        $action->execute($this->qa);
    }
}

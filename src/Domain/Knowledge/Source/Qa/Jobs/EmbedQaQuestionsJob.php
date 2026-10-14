<?php

declare(strict_types=1);

namespace Domain\Knowledge\Source\Qa\Jobs;

use Domain\Knowledge\Source\Qa\Actions\EmbedQaQuestionsAction;
use Domain\Knowledge\Source\Qa\Models\AgentQuestionAnswer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Embeds the questions of one curated Q&A entry, off the request path.
 */
class EmbedQaQuestionsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 60];

    public bool $deleteWhenMissingModels = true;

    public function __construct(
        public AgentQuestionAnswer $qa,
    ) {
        $this->onQueue('ingestion.chunks');
    }

    public function handle(EmbedQaQuestionsAction $action): void
    {
        $action->execute($this->qa);
    }
}

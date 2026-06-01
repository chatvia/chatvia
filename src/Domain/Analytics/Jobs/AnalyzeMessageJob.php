<?php

namespace Domain\Analytics\Jobs;

use Domain\Analytics\Actions\AnalyzeMessageAction;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class AnalyzeMessageJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $externalMessageId)
    {
        $this->onQueue('analytics');
    }

    public function handle(AnalyzeMessageAction $action): void
    {
        $action->execute($this->externalMessageId);
    }
}

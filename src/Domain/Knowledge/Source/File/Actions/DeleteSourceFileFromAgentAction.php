<?php

namespace Domain\Knowledge\Source\File\Actions;

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Ingestion\Services\Ingestion;
use Domain\Knowledge\Source\Enums\SourceStatus;
use Domain\Knowledge\Source\File\Models\SourceFile;

class DeleteSourceFileFromAgentAction
{
    public function __construct(private readonly Ingestion $ingestion) {}

    public function execute(Agent $agent, SourceFile $file): void
    {
        if ($file->status === SourceStatus::Processing->value) {
            $this->ingestion->cancel($file);
        }

        $file->agents()->detach($agent->id);
        $file->delete();
    }
}

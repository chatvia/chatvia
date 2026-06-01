<?php

namespace Domain\Agents\Actions\Sources;

use Domain\Agents\Models\Agent;
use Domain\Sources\Files\Models\SourceFile;

class LinkSourceFileToAgentAction
{
    public function execute(Agent $agent, SourceFile $file): void
    {
        $agent->files()->attach($file->id);
    }
}

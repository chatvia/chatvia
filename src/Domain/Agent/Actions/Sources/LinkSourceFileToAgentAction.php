<?php

namespace Domain\Agent\Actions\Sources;

use Domain\Agent\Models\Agent;
use Domain\Source\File\Models\SourceFile;

class LinkSourceFileToAgentAction
{
    public function execute(Agent $agent, SourceFile $file): void
    {
        $agent->files()->attach($file->id);
    }
}

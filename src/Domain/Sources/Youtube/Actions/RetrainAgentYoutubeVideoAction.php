<?php

namespace Domain\Sources\Youtube\Actions;

use Domain\Agents\Models\Agent;
use Domain\Sources\Youtube\Models\SourceYoutubeVideo;

class RetrainAgentYoutubeVideoAction
{
    public function execute(Agent $agent, SourceYoutubeVideo $video): void
    {
        $video->retrain($agent->id);
    }
}

<?php

namespace Domain\Source\Youtube\Actions;

use Domain\Agent\Models\Agent;
use Domain\Source\Youtube\Models\SourceYoutubeVideo;

class RetrainAgentYoutubeVideoAction
{
    public function execute(Agent $agent, SourceYoutubeVideo $video): void
    {
        $video->retrain($agent->id);
    }
}

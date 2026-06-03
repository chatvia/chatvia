<?php

namespace Domain\Knowledge\Source\Youtube\Actions;

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Source\Youtube\Models\SourceYoutubeVideo;

class RetrainAgentYoutubeVideoAction
{
    public function execute(Agent $agent, SourceYoutubeVideo $video): void
    {
        $video->retrain($agent->id);
    }
}

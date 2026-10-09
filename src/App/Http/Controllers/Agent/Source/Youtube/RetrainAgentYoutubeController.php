<?php

namespace App\Http\Controllers\Agent\Source\Youtube;

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Source\Youtube\Models\SourceYoutubeVideo;
use Illuminate\Http\JsonResponse;

/**
 * Re-ingests a YouTube video source attached to an agent.
 */
class RetrainAgentYoutubeController
{
    public function __invoke(Agent $agent, SourceYoutubeVideo $youtube): JsonResponse
    {
        abort_unless($agent->youtubeVideos()->whereKey($youtube->id)->exists(), 404);

        $youtube->retrain();

        return response()->json(['message' => __('YouTube video retraining started.')]);
    }
}

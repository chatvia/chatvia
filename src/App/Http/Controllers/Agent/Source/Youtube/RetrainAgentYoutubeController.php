<?php

namespace App\Http\Controllers\Agent\Source\Youtube;

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Source\Youtube\Models\SourceYoutubeVideo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Re-ingest a YouTube video source attached to an agent.
 */
class RetrainAgentYoutubeController
{
    public function __invoke(Request $request, Agent $agent, SourceYoutubeVideo $youtube): JsonResponse
    {
        abort_if($request->user()->cannot('update', $agent), 404);
        abort_unless(
            $agent->youtubeVideos()->where('source_youtube_videos.id', $youtube->id)->exists(),
            404,
        );

        $youtube->retrain($agent->id);

        return response()->json(['message' => 'YouTube video retraining started.']);
    }
}

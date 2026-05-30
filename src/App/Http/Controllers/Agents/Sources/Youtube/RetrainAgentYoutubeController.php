<?php

namespace App\Http\Controllers\Agents\Sources\Youtube;

use Domain\Agents\Models\Agent;
use Domain\Sources\Youtube\Actions\RetrainAgentYoutubeVideoAction;
use Domain\Sources\Youtube\Models\SourceYoutubeVideo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Re-ingest a YouTube video source attached to an agent.
 */
class RetrainAgentYoutubeController
{
    public function __invoke(Request $request, Agent $agent, SourceYoutubeVideo $youtube, RetrainAgentYoutubeVideoAction $retrain): JsonResponse
    {
        abort_if($request->user()->cannot('update', $agent), 404);
        abort_unless(
            $agent->youtubeVideos()->where('source_youtube_videos.id', $youtube->id)->exists(),
            404,
        );

        $retrain->execute($agent, $youtube);

        return response()->json(['message' => 'YouTube video retraining started.']);
    }
}

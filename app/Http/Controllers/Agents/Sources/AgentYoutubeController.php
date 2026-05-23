<?php

namespace App\Http\Controllers\Agents\Sources;

use App\Data\Resources\Agent\AgentResourceData;
use App\Models\Agent;
use App\Models\SourceYoutubeVideo;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AgentYoutubeController
{
    public function index(Request $request, Agent $agent): Response|Collection
    {
        if ($request->wantsJson()) {
            abort_if($request->user()->cannot('view', $agent), 404);

            return $agent
                ->youtubeVideos()
                ->select('source_youtube_videos.id', 'url', 'title', 'status', 'failure_reason', 'source_youtube_videos.created_at')
                ->latest('source_youtube_videos.created_at')
                ->get();
        }

        return Inertia::render('agents/Sources/Youtube', [
            'agent' => AgentResourceData::fromModel($agent),
        ]);
    }

    public function store(Request $request, Agent $agent): JsonResponse
    {
        abort_if($request->user()->cannot('update', $agent), 404);

        $request->validate([
            'url' => ['required', 'url', 'regex:/^(https?:\/\/)?(www\.)?(youtube\.com|youtu\.?be)\/.+$/'],
        ], [
            'url.regex' => 'Please enter a valid YouTube URL.',
        ]);

        $url = $request->input('url');

        if (! $this->extractVideoId($url)) {
            return response()->json([
                'message' => 'Could not extract video ID from URL.',
                'errors' => ['url' => ['Invalid YouTube URL format.']],
            ], 422);
        }

        $existing = $agent->youtubeVideos()
            ->where('source_youtube_videos.url', $url)
            ->exists();

        if ($existing) {
            return response()->json([
                'message' => 'This video has already been added.',
                'errors' => ['url' => ['This YouTube video has already been added to this agent.']],
            ], 422);
        }

        /** @var SourceYoutubeVideo $youtubeVideo */
        $youtubeVideo = SourceYoutubeVideo::create([
            'user_id' => $request->user()->id,
            'url' => $url,
            'length' => 0, // updated during ingestion
            'status' => 'pending',
        ]);

        $agent->youtubeVideos()->attach($youtubeVideo->id);

        $youtubeVideo->startTraining($agent->id);

        return response()->json(['message' => 'YouTube video added successfully.']);
    }

    public function destroy(Request $request, Agent $agent, SourceYoutubeVideo $youtube): JsonResponse
    {
        abort_if($request->user()->cannot('update', $agent), 404);
        abort_unless(
            $agent->youtubeVideos()->where('source_youtube_videos.id', $youtube->id)->exists(),
            404,
        );

        $youtube->chunks()->where('agent_id', $agent->id)->delete();

        $otherAgents = $youtube->agents()->where('agents.id', '!=', $agent->id)->exists();

        if (! $otherAgents) {
            $youtube->delete();
        } else {
            $agent->youtubeVideos()->detach($youtube->id);
        }

        return response()->json(['message' => 'YouTube video deleted successfully.']);
    }

    public function retrain(Request $request, Agent $agent, SourceYoutubeVideo $youtube): JsonResponse
    {
        abort_if($request->user()->cannot('update', $agent), 404);
        abort_unless(
            $agent->youtubeVideos()->where('source_youtube_videos.id', $youtube->id)->exists(),
            404,
        );

        $youtube->retrain($agent->id);

        return response()->json(['message' => 'YouTube video retraining started.']);
    }

    private function extractVideoId(string $url): ?string
    {
        $pattern = '/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/';

        return preg_match($pattern, $url, $matches) ? $matches[1] : null;
    }
}

<?php

namespace App\Http\Controllers\Agents\Sources\Youtube;

use App\Models\Agent;
use App\Models\AgentYoutubeVideo;
use Illuminate\Http\Request;

class AgentKnowledgeYoutubeStoreController
{
    public function __invoke(Request $request, Agent $agent)
    {
        abort_if(
            boolean: $request->user()->cannot('update', $agent),
            code: 404,
        );

        $request->validate([
            'url' => ['required', 'url', 'regex:/^(https?:\/\/)?(www\.)?(youtube\.com|youtu\.?be)\/.+$/'],
        ], [
            'url.regex' => 'Please enter a valid YouTube URL.',
        ]);

        $url = $request->input('url');

        // Extract video ID from URL
        $videoId = $this->extractVideoId($url);

        if (! $videoId) {
            return response()->json([
                'message' => 'Could not extract video ID from URL.',
                'errors' => [
                    'url' => ['Invalid YouTube URL format.'],
                ],
            ], 422);
        }

        // Check if video already exists for this agent
        $existingVideo = $agent->youtubeVideos()
            ->where('video_id', $videoId)
            ->first();

        if ($existingVideo) {
            return response()->json([
                'message' => 'This video has already been added.',
                'errors' => [
                    'url' => ['This YouTube video has already been added to this agent.'],
                ],
            ], 422);
        }

        /** @var AgentYoutubeVideo $youtubeVideo */
        $youtubeVideo = $agent->youtubeVideos()->create([
            'user_id' => $request->user()->id,
            'url' => $url,
            'video_id' => $videoId,
            'status' => 'pending',
        ]);

        $youtubeVideo->startTraining();

        return response()->json([
            'message' => 'YouTube video added successfully.',
        ]);
    }

    private function extractVideoId(string $url): ?string
    {
        $pattern = '/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/';

        if (preg_match($pattern, $url, $matches)) {
            return $matches[1];
        }

        return null;
    }
}

<?php

namespace App\Http\Controllers\Agent\Source\Youtube;

use App\Http\Requests\Agent\Source\Youtube\AddYoutubeVideoRequest;
use App\Identity\Workspace\Models\User;
use Domain\Agent\Data\Resources\Account\AgentResourceData;
use Domain\Agent\Models\Agent;
use Domain\Knowledge\Source\Youtube\Actions\AddAgentYoutubeVideoAction;
use Domain\Knowledge\Source\Youtube\Actions\DeleteAgentYoutubeVideoAction;
use Domain\Knowledge\Source\Youtube\Models\SourceYoutubeVideo;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Support\Exceptions\DomainValidationException;

/**
 * The agent's YouTube sources: the page, the list, adding and removing a video.
 * Behind the `youtube_sources` feature flag (video ingestion is not built yet).
 * Agent access is checked by the route middleware.
 */
class AgentYoutubeController
{
    public function index(Request $request, Agent $agent): Response|Collection
    {
        if ($request->wantsJson()) {
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

    public function store(AddYoutubeVideoRequest $request, Agent $agent, AddAgentYoutubeVideoAction $add): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        try {
            $add->execute($agent, $user, $request->videoUrl());
        } catch (DomainValidationException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => [$e->field => [$e->getMessage()]],
            ], 422);
        }

        return response()->json(['message' => __('YouTube video added successfully.')]);
    }

    public function destroy(Agent $agent, SourceYoutubeVideo $youtube, DeleteAgentYoutubeVideoAction $delete): JsonResponse
    {
        abort_unless($agent->youtubeVideos()->whereKey($youtube->id)->exists(), 404);

        $delete->execute($agent, $youtube);

        return response()->json(['message' => __('YouTube video deleted successfully.')]);
    }
}

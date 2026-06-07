<?php

namespace App\Http\Controllers\Agent\Source\Youtube;

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

    public function store(Request $request, Agent $agent, AddAgentYoutubeVideoAction $add): JsonResponse
    {
        abort_if($request->user()->cannot('update', $agent), 404);

        $request->validate([
            'url' => ['required', 'url', 'regex:/^(https?:\/\/)?(www\.)?(youtube\.com|youtu\.?be)\/.+$/'],
        ], ['url.regex' => 'Please enter a valid YouTube URL.']);

        /** @var User $user */
        $user = $request->user();

        try {
            $add->execute($agent, $user, $request->string('url')->toString());
        } catch (DomainValidationException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => [$e->field => [$e->getMessage()]],
            ], 422);
        }

        return response()->json(['message' => 'YouTube video added successfully.']);
    }

    public function destroy(Request $request, Agent $agent, SourceYoutubeVideo $youtube, DeleteAgentYoutubeVideoAction $delete): JsonResponse
    {
        abort_if($request->user()->cannot('update', $agent), 404);
        abort_unless(
            $agent->youtubeVideos()->where('source_youtube_videos.id', $youtube->id)->exists(),
            404,
        );

        $delete->execute($agent, $youtube);

        return response()->json(['message' => 'YouTube video deleted successfully.']);
    }
}

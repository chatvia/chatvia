<?php

namespace Domain\Knowledge\Source\Youtube\Actions;

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Knowledge\Source\Enums\SourceStatus;
use Domain\Knowledge\Source\Youtube\Models\SourceYoutubeVideo;
use Illuminate\Support\Facades\DB;
use Support\Exceptions\DomainValidationException;

/**
 * Adds a YouTube video as a knowledge source of an agent. The video is keyed
 * to the agent's owner (the workspace) and stays pending: video ingestion is
 * not built yet.
 */
class AddAgentYoutubeVideoAction
{
    /**
     * @throws DomainValidationException
     */
    public function execute(Agent $agent, User $user, string $url): SourceYoutubeVideo
    {
        if (! $this->extractVideoId($url)) {
            throw new DomainValidationException('url', __('Could not extract video ID from URL.'));
        }

        $exists = $agent->youtubeVideos()
            ->where('source_youtube_videos.url', $url)
            ->exists();

        if ($exists) {
            throw new DomainValidationException('url', __('This YouTube video has already been added to this agent.'));
        }

        /** @var SourceYoutubeVideo $video */
        $video = DB::transaction(function () use ($agent, $url): SourceYoutubeVideo {
            $video = SourceYoutubeVideo::query()->create([
                // Knowledge belongs to the workspace, not whoever added it — key to
                // the agent's owner so an employee's deletion can't cascade it away.
                'user_id' => $agent->user_id,
                'url' => $url,
                'length' => 0,
                'status' => SourceStatus::Pending,
            ]);

            $agent->youtubeVideos()->attach($video->id);

            return $video;
        });

        // YoutubeIngestionJob is not yet implemented (the prior path on the model
        // was a TODO no-op). When the job lands, dispatch it here.

        return $video;
    }

    private function extractVideoId(string $url): ?string
    {
        $pattern = '/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/';

        return preg_match($pattern, $url, $matches) ? $matches[1] : null;
    }
}

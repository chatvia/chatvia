<?php

namespace Domain\Knowledge\Source\Youtube\Actions;

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Knowledge\Source\Youtube\Models\SourceYoutubeVideo;
use Domain\Membership\Team\Exceptions\InviteRejectedException;

class AddAgentYoutubeVideoAction
{
    /**
     * @throws InviteRejectedException
     */
    public function execute(Agent $agent, User $user, string $url): SourceYoutubeVideo
    {
        if (! $this->extractVideoId($url)) {
            throw new InviteRejectedException('url', 'Could not extract video ID from URL.');
        }

        $exists = $agent->youtubeVideos()
            ->where('source_youtube_videos.url', $url)
            ->exists();

        if ($exists) {
            throw new InviteRejectedException('url', 'This YouTube video has already been added to this agent.');
        }

        /** @var SourceYoutubeVideo $video */
        $video = SourceYoutubeVideo::create([
            'user_id' => $user->id,
            'url' => $url,
            'length' => 0,
            'status' => 'pending',
        ]);

        $agent->youtubeVideos()->attach($video->id);
        $video->startTraining($agent->id);

        return $video;
    }

    private function extractVideoId(string $url): ?string
    {
        $pattern = '/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/';

        return preg_match($pattern, $url, $matches) ? $matches[1] : null;
    }
}

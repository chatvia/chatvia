<?php

namespace Domain\Agents\Observers;

use Domain\Agents\Models\Agent;
use Domain\Agents\Models\Llm;
use Domain\Agents\Services\Agent as AgentHelper;
use Domain\Chat\Models\ChatSession;
use Illuminate\Support\Facades\Storage;

class AgentObserver
{
    public function created(Agent $agent): void
    {
        AgentHelper::cacheAgentName($agent->sub_domain);

        $user = $agent->user;

        $defaultLlm = Llm::query()
            ->select('id')
            ->where('is_default', true)
            ->value('id');

        $llms = $user->llms();
        $agentLlms = $agent->isStandalone() ? $llms->pluck('id')->toArray() : [$defaultLlm];

        $llmIds = Llm::query()
            ->select('id')
            ->whereIn('id', $agentLlms)
            ->get()
            ->pluck('id')
            ->toArray();

        $agent
            ->llms()
            ->attach(
                $llmIds
            );
    }

    public function updated(Agent $agent): void
    {
        AgentHelper::cacheAgentName($agent->sub_domain);
    }

    /**
     * Handle the Agent "deleting" event.
     * This fires BEFORE the agent is deleted, so we can still query relationships.
     */
    public function deleting(Agent $agent): void
    {
        // Clean up files that are only used by this agent
        $agent->files->each(function ($file) use ($agent): void {
            // Check if this file is only attached to this agent
            if ($file->agents()->count() === 1) {
                // Delete the file (observer will handle storage cleanup)
                $file->delete();
            } else {
                // Just detach from this agent
                $agent->files()->detach($file->id);
            }
        });

        // Clean up websites that are only used by this agent
        $agent->websites->each(function ($website) use ($agent): void {
            // Check if this website is only attached to this agent
            if ($website->agents()->count() === 1) {
                // Delete the website (observer will handle related cleanup)
                $website->delete();
            } else {
                // Just detach from this agent
                $agent->websites()->detach($website->id);
            }
        });

        // Clean up texts that are only used by this agent
        $agent->texts->each(function ($text) use ($agent): void {
            // Check if this text is only attached to this agent
            if ($text->agents()->count() === 1) {
                // Delete the text (observer will handle related cleanup)
                $text->delete();
            } else {
                // Just detach from this agent
                $agent->texts()->detach($text->id);
            }
        });

        // Clean up YouTube videos that are only used by this agent
        $agent->youtubeVideos->each(function ($video) use ($agent): void {
            // Check if this video is only attached to this agent
            if ($video->agents()->count() === 1) {
                // Delete the video (observer will handle related cleanup)
                $video->delete();
            } else {
                // Just detach from this agent
                $agent->youtubeVideos()->detach($video->id);
            }
        });

        $agent->chatSessions->each(function (ChatSession $chatSession): void {
            app(\Domain\Chat\Services\Memory\ChatSession::class)->deleteSession($chatSession->id);
            $chatSession->delete();
        });
    }

    public function deleted(Agent $agent): void
    {
        Storage::deleteDirectory('users/'.$agent->user_id.'/agents/'.$agent->id);
    }

    public function restored(Agent $agent): void {}

    /**
     * Handle the Agent "force deleted" event.
     */
    public function forceDeleted(Agent $agent): void
    {
        $this->deleted($agent);
    }
}

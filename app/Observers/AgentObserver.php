<?php

namespace App\Observers;

use App\Models\Agent;
use App\Models\ChatSession;
use App\Models\Llm;
use App\Models\User;
use App\Services\Agents\Agent as AgentHelper;
use App\Services\Memory\ChatMemory;
use Illuminate\Support\Facades\Storage;

class AgentObserver
{
    public function created(Agent $agent): void
    {
        AgentHelper::cacheAgentName($agent->sub_domain);

        /** @var User $user */
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
        $agent->files->each(function ($file) use ($agent) {
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
        $agent->websites->each(function ($website) use ($agent) {
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
        $agent->texts->each(function ($text) use ($agent) {
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
        $agent->youtubeVideos->each(function ($video) use ($agent) {
            // Check if this video is only attached to this agent
            if ($video->agents()->count() === 1) {
                // Delete the video (observer will handle related cleanup)
                $video->delete();
            } else {
                // Just detach from this agent
                $agent->youtubeVideos()->detach($video->id);
            }
        });

        $agent->chatSessions->each(function (ChatSession $chatSession) use ($agent) {
            app(\App\Services\Memory\ChatSession::class)->deleteSession($chatSession->id);
            $chatSession->delete();
        });
    }

    public function deleted(Agent $agent): void
    {
        Storage::deleteDirectory('users/'.$agent->user_id.'/agents/'.$agent->id);
    }

    public function restored(Agent $agent): void
    {

    }

    /**
     * Handle the Agent "force deleted" event.
     */
    public function forceDeleted(Agent $agent): void
    {
        $this->deleted($agent);
    }

    private function decreaseAgentLimit(Agent $agent): void
    {
        $this->updateAgentQuota($agent, false);
    }

    private function increaseAgentLimit(Agent $agent): void
    {
        $this->updateAgentQuota($agent, true);
    }

    private function updateAgentQuota(Agent $agent, bool $increment): void
    {
        /** @var User $user */
        $user = $agent->user;

        $agentTypeField = 'agents_limit'; // Unified agents limit

        $operator = $increment ? '+' : '-';

        //        $user->update([
        //            $agentTypeField => DB::raw($agentTypeField.' '.$operator.' 1'),
        //        ]);
    }
}

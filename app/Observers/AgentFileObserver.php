<?php

namespace App\Observers;

use App\Models\AgentFile;
use App\Models\User;

class AgentFileObserver
{
    public function created(AgentFile $agentFile): void
    {
        $agentFile->load('user', 'user.credit', 'user.credit.usage');

        /** @var User $user */
        $user = $agentFile->user;

        $usage = $user->usage();

        if ($agentFile->isAudioFile()) {
            $usage->increment('minutes', $agentFile->minutes);
        } else {
            $usage->increment('pages', $agentFile->number_of_pages);
        }
    }

    public function updated(AgentFile $agentFile): void {}

    public function deleted(AgentFile $agentFile): void
    {
        $agentFile->load('user', 'user.credit', 'user.usage');

        /** @var User $user */
        $user = $agentFile->user;

        $usage = $user->usage();

        if ($agentFile->isAudioFile()) {
            $usage->decrement('minutes', $agentFile->minutes);
        } else {
            $usage->decrement('pages', $agentFile->number_of_pages);
        }
    }

    public function restored(AgentFile $agentFile): void {}

    public function forceDeleted(AgentFile $agentFile): void
    {
        $this->deleted($agentFile);
    }
}

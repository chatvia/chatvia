<?php

namespace App\Observers;

use App\Models\AgentFile;
use App\Models\Usage;
use App\Models\User;

class AgentFileObserver
{
    public function created(AgentFile $agentFile): void
    {
        $agentFile->load('user', 'user.credit', 'user.credit.usage');

        /** @var User $user */
        $user = $agentFile->user;

        /** @var Usage $usage */
        $usage = $user->usage;

        if ($agentFile->isAudioFile()) {
            $usage->increment('minutes', $agentFile->length);
        } else {
            $usage->increment('pages', $agentFile->length);
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
            $usage->decrement('minutes', $agentFile->length);
        } else {
            $usage->decrement('pages', $agentFile->length);
        }
    }

    public function restored(AgentFile $agentFile): void {}

    public function forceDeleted(AgentFile $agentFile): void
    {
        $this->deleted($agentFile);
    }
}

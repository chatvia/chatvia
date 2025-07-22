<?php

namespace App\Observers;

use App\Models\AgentFile;
use App\Services\Billing\QuotaService;

class AgentFileObserver
{
    public function __construct(
        private QuotaService $quotaService
    ) {}

    public function created(AgentFile $agentFile): void
    {
        $user = $agentFile->user;

        if (! $user) {
            return;
        }

        // Track different types of file usage
        if ($agentFile->isAudioFile() && $agentFile->minutes) {
            $this->quotaService->incrementUsage($user, 'minutes', $agentFile->minutes);
        } elseif ($agentFile->pages) {
            // For document files, track pages
            $this->quotaService->incrementUsage($user, 'pages', $agentFile->pages);
        }
    }

    public function updated(AgentFile $agentFile): void
    {
        $user = $agentFile->user;

        if (! $user || ! $agentFile->wasChanged(['pages', 'minutes'])) {
            return;
        }

        // Handle changes in pages/minutes after processing
        $changes = [];

        if ($agentFile->wasChanged('pages')) {
            $oldPages = $agentFile->getOriginal('pages') ?? 0;
            $newPages = $agentFile->pages ?? 0;
            $pagesDiff = $newPages - $oldPages;

            if ($pagesDiff !== 0) {
                $changes['pages'] = $pagesDiff;
            }
        }

        if ($agentFile->wasChanged('minutes')) {
            $oldMinutes = $agentFile->getOriginal('minutes') ?? 0;
            $newMinutes = $agentFile->minutes ?? 0;
            $minutesDiff = $newMinutes - $oldMinutes;

            if ($minutesDiff !== 0) {
                $changes['minutes'] = $minutesDiff;
            }
        }

        if (! empty($changes)) {
            $this->quotaService->bulkUpdateUsage($user, $changes);
        }
    }

    public function deleted(AgentFile $agentFile): void
    {
        $user = $agentFile->user;

        if (! $user) {
            return;
        }

        // Free up quota when files are deleted
        $changes = [];

        if ($agentFile->pages) {
            $changes['pages'] = -$agentFile->pages; // Negative to decrement
        }

        if ($agentFile->minutes) {
            $changes['minutes'] = -$agentFile->minutes; // Negative to decrement
        }

        if (! empty($changes)) {
            $this->quotaService->bulkUpdateUsage($user, $changes);
        }
    }

    public function restored(AgentFile $agentFile): void
    {
        // When a file is restored, re-add it to quota
        $this->created($agentFile);
    }

    public function forceDeleted(AgentFile $agentFile): void
    {
        // Same as regular deletion
        $this->deleted($agentFile);
    }
}

<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function (): void {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('horizon:snapshot')->everyFiveMinutes();

// Daily cleanup of visitor-uploaded chat attachments. Keeps the Scaleway
// public bucket from accumulating PDFs indefinitely. The 30-day window is
// the default; override with `--days=N` for a one-off pass.
Schedule::command('app:prune-chat-attachments')->dailyAt('03:00');

// Soft-deletes conversations past their per-plan retention horizon. Runs
// after the attachment prune so the two passes don't fight over Horizon
// workers — and so any conversation-cascade attachment deletions happen
// before the next attachment sweep.
Schedule::command('app:prune-expired-conversations')->dailyAt('03:30');

// Removes human-in-the-loop tool-approval checkpoints once they're resolved or
// their TTL lapses. Each row stores a full conversation snapshot, so they would
// bloat the table fast; this runs after the conversation prune.
Schedule::command('app:prune-tool-approvals')->dailyAt('03:45');

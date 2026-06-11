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

// Governance audit retention: trims tool-call audit rows in `agent_activity`
// past config('chatvia.governance.retention_days') (default 90).
Schedule::command('app:prune-agent-activity')->dailyAt('04:00');

// Consolidated end-of-life conversation analysis: one LLM call per conversation
// sets topic(s), sentiment, tone and language and records emergent-topic
// suggestions. Each conversation is analyzed once, ~6h after creation
// (config: analytics.analysis.scan_age_hours); the hourly cadence just picks up
// whatever has newly crossed that age.
Schedule::command('app:analyze-conversations')->hourly();

// Drain the Redis token-usage buffer (RecordLlmTokenUsage) into
// user_usages.tokens — one UPDATE per active workspace per minute instead of
// one contended row-increment per LLM op on the request path.
Schedule::command('app:flush-token-usage-buffer')->everyMinute();

// Retrain the vchordrq k-means partitioning on the chunks indexes against the
// CURRENT rows (migrate:fresh builds them on an empty table, so the initial
// centroids are placeholders). CONCURRENTLY, so retrieval and ingestion stay
// unblocked; weekly is a safety net during the growth phase — run it manually
// after any large bulk ingestion.
Schedule::command('vector:reindex')->sundays()->at('04:30');

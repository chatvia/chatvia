<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;
use MagicLink\MagicLink;

Schedule::command('horizon:snapshot')->everyFiveMinutes();

Schedule::command('telescope:prune --hours=24')->dailyAt('00:00');

// Refreshes the cached per-workspace message usage that the message-capacity
// gate reads between consumptions.
Schedule::command('app:cache-message-usage')->everyFiveMinutes();

Schedule::command('app:prune-expired-data-exports')->dailyAt('00:15');

// Daily cleanup of visitor-uploaded chat attachments: files whose
// conversation expired or was deleted, and uploads that never joined a
// conversation (after 30 days; override with `--days=N`). Rows and stored
// files are deleted together.
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

// Removes abandoned inline-form placeholders: pending `form_submissions` rows
// whose TTL has lapsed (the visitor never submitted). Once expired they can no
// longer be claimed and hold no captured answers, so they only bloat the table.
Schedule::command('app:prune-expired-forms')->everyTenMinutes();

// Governance audit retention: trims tool-call audit rows in `agent_activity`
// past config('chatvia.governance.retention_days') (default 90).
Schedule::command('app:prune-agent-activity')->dailyAt('04:00');

// Guardrail audit retention: same pattern for `guardrail_events`, enforcing
// config('chatvia.guardrails.retention_days') (default 90) — the knob was
// documented but nothing pruned the table.
Schedule::command('app:prune-guardrail-events')->dailyAt('04:15');

// Security audit retention: trims `security_audit_log` past the config default,
// honouring per-workspace `retention_audit_days` overrides clamped to the floor.
// Runs after the guardrail prune so the audit sweeps don't fight for workers.
Schedule::command('app:prune-security-audit-log')->dailyAt('04:25');

// Framework growth tables that have producers but had no reaper: finished
// ingestion/eval batches, failed jobs, year-expired Sanctum tokens, and
// consumed/expired magic links.
Schedule::command('queue:prune-batches --hours=48 --unfinished=72 --cancelled=72')->dailyAt('05:00');
Schedule::command('queue:prune-failed --hours=168')->dailyAt('05:05');
Schedule::command('sanctum:prune-expired --hours=24')->dailyAt('05:10');
Schedule::command('model:prune', ['--model' => [MagicLink::class]])->dailyAt('05:15');

// Consolidated end-of-life conversation analysis: one LLM call per conversation
// sets topic(s), sentiment, tone and language and records emergent-topic
// suggestions. Each conversation is analyzed once, ~6h after creation
// (config: analytics.analysis.scan_age_hours); the hourly cadence just picks up
// whatever has newly crossed that age.
Schedule::command('app:analyze-conversations')->hourly();

// Raw analytics-event retention: the rollup/metrics tables carry every
// dashboard aggregate, so the raw firehose only needs to cover the window
// where re-deriving (or debugging) per-event data is still useful.
Schedule::command('app:prune-analytics-events')->dailyAt('04:45');

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

// Off-host database backup: pg_dump (custom/compressed format) streamed to the
// private Scaleway bucket, keeping the newest 14 (~7 days at this cadence).
// Every 12 hours, offset from the 03:00–05:00 prune cluster. withoutOverlapping
// guards against a slow dump colliding with the next run; runInBackground keeps
// it off the scheduler's critical path.
Schedule::command('app:backup-database')
    ->twiceDaily(1, 13)
    ->withoutOverlapping()
    ->runInBackground();

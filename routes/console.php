<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;
use MagicLink\MagicLink;

// Every maintenance command below runs `withoutOverlapping()->onOneServer()`:
// with several app servers each would otherwise run the same sweep at the same
// minute, and a slow sweep must not start a second copy of itself. Commands
// that run more often than daily pass a lock expiry close to their interval, so
// a crashed run does not block the next ones for a whole day.

Schedule::command('horizon:snapshot')->everyFiveMinutes()->withoutOverlapping(5)->onOneServer();

Schedule::command('app:prune-expired-data-exports')->dailyAt('00:15')->withoutOverlapping()->onOneServer();

// Daily cleanup of chat uploads that never joined a conversation (after 30
// days; override with `--days=N`). A conversation's files go with the
// conversation.
Schedule::command('app:prune-chat-attachments')->dailyAt('03:00')->withoutOverlapping()->onOneServer();

// Soft-deletes conversations past their retention horizon. Runs
// after the attachment prune so the two passes don't fight over Horizon
// workers — and so any conversation-cascade attachment deletions happen
// before the next attachment sweep.
Schedule::command('app:prune-expired-conversations')->dailyAt('03:30')->withoutOverlapping()->onOneServer();

// Removes human-in-the-loop tool-approval checkpoints once they're resolved or
// their TTL lapses. Each row stores a full conversation snapshot, so they would
// bloat the table fast; this runs after the conversation prune.
Schedule::command('app:prune-tool-approvals')->dailyAt('03:45')->withoutOverlapping()->onOneServer();

// Removes abandoned inline-form placeholders: pending `form_submissions` rows
// whose TTL has lapsed (the visitor never submitted). Once expired they can no
// longer be claimed and hold no captured answers, so they only bloat the table.
Schedule::command('app:prune-expired-forms')->everyTenMinutes()->withoutOverlapping(10)->onOneServer();

// Governance audit retention: trims tool-call audit rows in `agent_activity`
// past config('chatvia.governance.retention_days') (default 90).
Schedule::command('app:prune-agent-activity')->dailyAt('04:00')->withoutOverlapping()->onOneServer();

// Guardrail audit retention: same pattern for `guardrail_events`, enforcing
// config('chatvia.guardrails.retention_days') (default 90) — the knob was
// documented but nothing pruned the table.
Schedule::command('app:prune-guardrail-events')->dailyAt('04:15')->withoutOverlapping()->onOneServer();

// Security audit retention: trims `security_audit_log` past the config default,
// honouring per-workspace `retention_audit_days` overrides clamped to the floor.
// Runs after the guardrail prune so the audit sweeps don't fight for workers.
Schedule::command('app:prune-security-audit-log')->dailyAt('04:25')->withoutOverlapping()->onOneServer();

// Raw analytics-event retention: the rollup/metrics tables carry every
// dashboard aggregate, so the raw firehose only needs to cover the window
// where re-deriving (or debugging) per-event data is still useful.
Schedule::command('app:prune-analytics-events')->dailyAt('04:45')->withoutOverlapping()->onOneServer();

// Per-workspace retention (Settings → Security → Retention), only for the
// workspaces that set a window. Visitor details are cleared after the
// conversation prune has removed orphan sessions; the analytics sweep runs
// after the install-wide raw-event prune.
Schedule::command('app:anonymize-chat-sessions')->dailyAt('03:50')->withoutOverlapping()->onOneServer();
Schedule::command('app:prune-leads')->dailyAt('04:05')->withoutOverlapping()->onOneServer();
Schedule::command('app:prune-workspace-analytics')->dailyAt('04:50')->withoutOverlapping()->onOneServer();

// Framework growth tables that have producers but had no reaper: finished
// ingestion/eval batches, failed jobs, year-expired Sanctum tokens, and
// consumed/expired magic links.
Schedule::command('queue:prune-batches --hours=48 --unfinished=72 --cancelled=72')->dailyAt('05:00')->withoutOverlapping()->onOneServer();
Schedule::command('queue:prune-failed --hours=168')->dailyAt('05:05')->withoutOverlapping()->onOneServer();
Schedule::command('sanctum:prune-expired --hours=24')->dailyAt('05:10')->withoutOverlapping()->onOneServer();
Schedule::command('model:prune', ['--model' => [MagicLink::class]])->dailyAt('05:15')->withoutOverlapping()->onOneServer();

// The `chat` cache store groups chat memory under tags. Redis keeps a sorted
// set per tag that lists its keys; entries stay in it after the keys expire,
// so the sets only grow until this removes the stale entries.
Schedule::command('cache:prune-stale-tags chat')->hourly()->withoutOverlapping(60)->onOneServer();

// Consolidated end-of-life conversation analysis: one LLM call per conversation
// sets topic(s), sentiment, tone and language and records emergent-topic
// suggestions. Each conversation is analyzed once, ~6h after creation
// (config: analytics.analysis.scan_age_hours); the hourly cadence just picks up
// whatever has newly crossed that age.
Schedule::command('app:analyze-conversations')->hourly()->withoutOverlapping(60)->onOneServer();

// Size the chunks vector indexes for the current rows: flat below 100k rows,
// then about 4·√N partitions, rebuilt side by side (CONCURRENTLY) when the
// rows have doubled. Cheap when nothing changed; run it by hand after a large
// bulk ingestion.
Schedule::command('vector:reindex')->sundays()->at('04:30')->withoutOverlapping()->onOneServer();

// Database backup: pg_dump (custom/compressed format) streamed to the private
// disk (this server, or the private bucket of Settings → Storage), keeping the
// newest 14 (~7 days at this cadence).
// Every 12 hours, offset from the 03:00–05:00 prune cluster. withoutOverlapping
// guards against a slow dump colliding with the next run; runInBackground keeps
// it off the scheduler's critical path.
Schedule::command('app:backup-database')
    ->twiceDaily(1, 13)
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground();

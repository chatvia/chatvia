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

Schedule::command('chatvia:prune:data-exports')->dailyAt('00:15')->withoutOverlapping()->onOneServer();

// Daily cleanup of chat uploads that never joined a conversation (after 30
// days; override with `--days=N`). A conversation's files go with the
// conversation.
Schedule::command('chatvia:prune:attachments')->dailyAt('03:00')->withoutOverlapping()->onOneServer();

// Soft-deletes conversations past their retention horizon. Runs
// after the attachment prune so the two passes don't fight over Horizon
// workers — and so any conversation-cascade attachment deletions happen
// before the next attachment sweep.
Schedule::command('chatvia:prune:conversations')->dailyAt('03:30')->withoutOverlapping()->onOneServer();

// Removes human-in-the-loop tool-approval checkpoints once they're resolved or
// their TTL lapses. Each row stores a full conversation snapshot, so they would
// bloat the table fast; this runs after the conversation prune.
Schedule::command('chatvia:prune:tool-approvals')->dailyAt('03:45')->withoutOverlapping()->onOneServer();

// Removes abandoned inline-form placeholders: pending `form_submissions` rows
// whose TTL has lapsed (the visitor never submitted). Once expired they can no
// longer be claimed and hold no captured answers, so they only bloat the table.
Schedule::command('chatvia:prune:forms')->everyTenMinutes()->withoutOverlapping(10)->onOneServer();

// Governance audit retention: trims tool-call audit rows in `agent_activity`
// past config('chatvia.governance.retention_days') (default 90).
Schedule::command('chatvia:prune:agent-activity')->dailyAt('04:00')->withoutOverlapping()->onOneServer();

// Guardrail audit retention: same pattern for `guardrail_events`, enforcing
// config('chatvia.guardrails.retention_days') (default 90) — the knob was
// documented but nothing pruned the table.
Schedule::command('chatvia:prune:guardrail-events')->dailyAt('04:15')->withoutOverlapping()->onOneServer();

// Visitor blocks that ended more than 30 days ago (`blocked_visitors`).
Schedule::command('chatvia:prune:visitor-blocks')->dailyAt('04:20')->withoutOverlapping()->onOneServer();

// Security audit retention: trims `security_audit_log` past the config default,
// honouring per-workspace `retention_audit_days` overrides clamped to the floor.
// Runs after the guardrail prune so the audit sweeps don't fight for workers.
Schedule::command('chatvia:prune:security-audit-log')->dailyAt('04:25')->withoutOverlapping()->onOneServer();

// Sign-in devices nobody used for 180 days (`user_known_devices`), and chatter
// and team invitations 30 days after they expired. Fixed periods.
Schedule::command('chatvia:prune:known-devices')->dailyAt('04:30')->withoutOverlapping()->onOneServer();
Schedule::command('chatvia:prune:invitations')->dailyAt('04:35')->withoutOverlapping()->onOneServer();

// Backstop for agent deletes whose purge failed every try: agents deleted more
// than a day ago are queued for the purge again.
Schedule::command('chatvia:agent:purge-deleted')->dailyAt('04:40')->withoutOverlapping()->onOneServer();

// Raw analytics retention: the rollups carry every dashboard aggregate, so the
// raw events, the per-conversation metrics and the session firsts only need to
// cover the window where per-event data is still useful.
Schedule::command('chatvia:prune:analytics-events')->dailyAt('04:45')->withoutOverlapping()->onOneServer();

// Websites set to refresh on their own (daily, weekly, monthly) are crawled
// and trained again once their interval has passed.
Schedule::command('chatvia:knowledge:refresh-websites')->hourlyAt(20)->withoutOverlapping()->onOneServer();

// Asks chatvia.ai whether the saved license key is still active (nothing to
// ask without one). The minute comes from the app URL, so installs spread out
// over the hour instead of calling at the same moment.
Schedule::command('chatvia:license:check')
    ->dailyAt(sprintf('06:%02d', crc32((string) config('app.url')) % 60))
    ->withoutOverlapping()
    ->onOneServer();

// Updates each model's facts (reasoning, effort values, temperature) from the
// models.dev catalog, fetched only when it changed. Spread over the hour like
// the license check.
Schedule::command('chatvia:llm:sync-facts')
    ->dailyAt(sprintf('05:%02d', crc32((string) config('app.url')) % 60))
    ->withoutOverlapping()
    ->onOneServer();

// Offers a model again once the provider that stopped serving it lists it
// again. Reads only the lists of providers with such a model.
Schedule::command('chatvia:llm:check-listing')->hourlyAt(35)->withoutOverlapping(60)->onOneServer();

// Per-workspace retention (Settings → Security → Retention), only for the
// workspaces that set a window. Visitor details are cleared after the
// conversation prune has removed orphan sessions; the analytics sweep runs
// after the install-wide raw-event prune.
Schedule::command('chatvia:privacy:anonymize-sessions')->dailyAt('03:50')->withoutOverlapping()->onOneServer();
Schedule::command('chatvia:prune:leads')->dailyAt('04:05')->withoutOverlapping()->onOneServer();
Schedule::command('chatvia:prune:workspace-analytics')->dailyAt('04:50')->withoutOverlapping()->onOneServer();

// The dashboard's notification center keeps a week by default (Settings →
// Workspaces → Notifications).
Schedule::command('chatvia:prune:notifications')->dailyAt('04:55')->withoutOverlapping()->onOneServer();

// Framework growth tables that have producers but had no reaper: finished
// ingestion/eval batches, failed jobs, year-expired Sanctum tokens, and
// consumed/expired magic links.
Schedule::command('queue:prune-batches --hours=48 --unfinished=72 --cancelled=72')->dailyAt('05:00')->withoutOverlapping()->onOneServer();
Schedule::command('queue:prune-failed --hours=168')->dailyAt('05:05')->withoutOverlapping()->onOneServer();
Schedule::command('sanctum:prune-expired --hours=24')->dailyAt('05:10')->withoutOverlapping()->onOneServer();
Schedule::command('model:prune', ['--model' => [MagicLink::class]])->dailyAt('05:15')->withoutOverlapping()->onOneServer();

// Expired password reset tokens: the staff broker's table, then the chatters'
// own table (chatters have no Laravel broker).
Schedule::command('auth:clear-resets users')->dailyAt('05:25')->withoutOverlapping()->onOneServer();
Schedule::command('chatvia:prune:chatter-password-resets')->dailyAt('05:30')->withoutOverlapping()->onOneServer();

// Spectra's AI call log keeps the days set in Settings → Observability.
// It runs while Spectra does not record too, so an old log still empties.
Schedule::command('spectra:prune', ['--hours' => max(1, (int) config('chatvia.spectra.retention_days')) * 24])->dailyAt('05:20')->withoutOverlapping()->onOneServer();

// The `chat` cache store groups chat memory under tags. Redis keeps a sorted
// set per tag that lists its keys; entries stay in it after the keys expire,
// so the sets only grow until this removes the stale entries.
Schedule::command('cache:prune-stale-tags chat')->hourly()->withoutOverlapping(60)->onOneServer();

// Consolidated end-of-life conversation analysis: one LLM call per conversation
// sets topic(s), sentiment, tone and language and records emergent-topic
// suggestions. Each conversation is analyzed once, ~6h after creation
// (config: analytics.analysis.scan_age_hours); the hourly cadence just picks up
// whatever has newly crossed that age.
Schedule::command('chatvia:analytics:analyze-conversations')->hourly()->withoutOverlapping(60)->onOneServer();

// The automatic backup (Settings → Backups): the database and Redis in one
// archive on the private disk. Checked every hour; the command backs up only
// when the backup is on and as old as the chosen frequency (BackupSchedule).
// withoutOverlapping guards against a slow backup colliding with the next
// check; runInBackground keeps it off the scheduler's critical path.
Schedule::command('chatvia:backup:create --if-due')
    ->hourly()
    ->withoutOverlapping(8 * 60)
    ->onOneServer()
    ->runInBackground();

<?php

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;

/** The cron expression the scheduler runs $command on, or null when it is not scheduled. */
function scheduleExpressionOf(string $command): ?string
{
    $event = collect(app(Schedule::class)->events())
        ->first(fn (Event $event): bool => str_ends_with((string) $event->command, ' '.$command)
            || str_contains((string) $event->command, ' '.$command.' '));

    return $event?->expression;
}

it('schedules every recurring command from routes/console.php', function (string $command, string $expression): void {
    expect(scheduleExpressionOf($command))->toBe($expression);
})->with([
    ['horizon:snapshot', '*/5 * * * *'],
    ['app:prune-expired-data-exports', '15 0 * * *'],
    ['app:prune-chat-attachments', '0 3 * * *'],
    ['app:prune-expired-conversations', '30 3 * * *'],
    ['app:prune-tool-approvals', '45 3 * * *'],
    ['app:prune-expired-forms', '*/10 * * * *'],
    ['app:prune-agent-activity', '0 4 * * *'],
    ['app:prune-guardrail-events', '15 4 * * *'],
    ['app:prune-security-audit-log', '25 4 * * *'],
    ['app:prune-analytics-events', '45 4 * * *'],
    ['app:refresh-websites', '20 * * * *'],
    ['app:anonymize-chat-sessions', '50 3 * * *'],
    ['app:prune-leads', '5 4 * * *'],
    ['app:prune-workspace-analytics', '50 4 * * *'],
    ['queue:prune-batches', '0 5 * * *'],
    ['queue:prune-failed', '5 5 * * *'],
    ['sanctum:prune-expired', '10 5 * * *'],
    ['model:prune', '15 5 * * *'],
    ['spectra:prune', '20 5 * * *'],
    ['cache:prune-stale-tags chat', '0 * * * *'],
    ['app:analyze-conversations', '0 * * * *'],
    ['vector:reindex', '30 4 * * 0'],
    ['app:backup-database', '0 1,13 * * *'],
]);

it('checks the license daily in the 6 o\'clock hour, at a minute of the install\'s own', function (): void {
    expect(scheduleExpressionOf('app:check-license'))->toMatch('/^[1-5]?[0-9] 6 \* \* \*$/');
});

it('schedules nothing the tests above do not name', function (): void {
    expect(app(Schedule::class)->events())->toHaveCount(24);
});

it('prunes the Spectra log after the days kept', function (): void {
    $command = collect(app(Schedule::class)->events())
        ->map(fn (Event $event): string => (string) $event->command)
        ->first(fn (string $command): bool => str_contains($command, 'spectra:prune'));

    // 30 days, the default of Settings → Platform → Spectra.
    expect($command)->toContain('--hours=720');
});

it('runs every scheduled command on one server without overlapping', function (): void {
    foreach (app(Schedule::class)->events() as $event) {
        expect($event->withoutOverlapping)->toBeTrue((string) $event->command)
            ->and($event->onOneServer)->toBeTrue((string) $event->command);
    }
});

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
    ['chatvia:prune:data-exports', '15 0 * * *'],
    ['chatvia:prune:attachments', '0 3 * * *'],
    ['chatvia:prune:conversations', '30 3 * * *'],
    ['chatvia:prune:tool-approvals', '45 3 * * *'],
    ['chatvia:prune:forms', '*/10 * * * *'],
    ['chatvia:prune:agent-activity', '0 4 * * *'],
    ['chatvia:prune:guardrail-events', '15 4 * * *'],
    ['chatvia:prune:visitor-blocks', '20 4 * * *'],
    ['chatvia:prune:security-audit-log', '25 4 * * *'],
    ['chatvia:prune:known-devices', '30 4 * * *'],
    ['chatvia:prune:invitations', '35 4 * * *'],
    ['chatvia:agent:purge-deleted', '40 4 * * *'],
    ['chatvia:prune:analytics-events', '45 4 * * *'],
    ['chatvia:knowledge:refresh-websites', '20 * * * *'],
    ['chatvia:privacy:anonymize-sessions', '50 3 * * *'],
    ['chatvia:prune:leads', '5 4 * * *'],
    ['chatvia:prune:workspace-analytics', '50 4 * * *'],
    ['chatvia:prune:notifications', '55 4 * * *'],
    ['queue:prune-batches', '0 5 * * *'],
    ['queue:prune-failed', '5 5 * * *'],
    ['sanctum:prune-expired', '10 5 * * *'],
    ['model:prune', '15 5 * * *'],
    ['auth:clear-resets users', '25 5 * * *'],
    ['chatvia:prune:chatter-password-resets', '30 5 * * *'],
    ['spectra:prune', '20 5 * * *'],
    ['cache:prune-stale-tags chat', '0 * * * *'],
    ['chatvia:analytics:analyze-conversations', '0 * * * *'],
    ['chatvia:db:backup', '0 1,13 * * *'],
]);

it('checks the license daily in the 6 o\'clock hour, at a minute of the install\'s own', function (): void {
    expect(scheduleExpressionOf('chatvia:license:check'))->toMatch('/^[1-5]?[0-9] 6 \* \* \*$/');
});

it('syncs the models\' facts daily in the 5 o\'clock hour, at a minute of the install\'s own', function (): void {
    expect(scheduleExpressionOf('chatvia:llm:sync-facts'))->toMatch('/^[1-5]?[0-9] 5 \* \* \*$/');
});

it('schedules nothing the tests above do not name', function (): void {
    expect(app(Schedule::class)->events())->toHaveCount(31);
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

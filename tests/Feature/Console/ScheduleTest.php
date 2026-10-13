<?php

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;

it('schedules every recurring command from routes/console.php', function (): void {
    $commands = collect(app(Schedule::class)->events())
        ->map(fn (Event $event): string => (string) $event->command)
        ->implode("\n");

    foreach ([
        'app:prune-expired-data-exports',
        'app:prune-chat-attachments',
        'app:backup-database',
        'cache:prune-stale-tags chat',
    ] as $command) {
        expect($commands)->toContain($command);
    }
});

it('runs every scheduled command on one server without overlapping', function (): void {
    foreach (app(Schedule::class)->events() as $event) {
        expect($event->withoutOverlapping)->toBeTrue((string) $event->command)
            ->and($event->onOneServer)->toBeTrue((string) $event->command);
    }
});

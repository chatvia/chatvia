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
    ] as $command) {
        expect($commands)->toContain($command);
    }
});

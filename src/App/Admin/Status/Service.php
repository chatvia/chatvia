<?php

declare(strict_types=1);

namespace App\Admin\Status;

use Filament\Support\Icons\Heroicon;

/**
 * A service the install needs, with a card on the Status page: the stores,
 * the processes that run beside the web server (each one its own container
 * in Docker), and the services it sends to. {@see ServiceChecks} checks it.
 */
enum Service: string
{
    case Database = 'database';
    case Redis = 'redis';
    case Queue = 'queue';
    case Scheduler = 'scheduler';
    case Realtime = 'realtime';
    case ServerMonitor = 'server_monitor';
    case Storage = 'storage';
    case Email = 'email';
    case Programs = 'programs';

    public function label(): string
    {
        return match ($this) {
            self::Database => __('Database'),
            self::Redis => __('Redis'),
            self::Queue => __('Queue'),
            self::Scheduler => __('Scheduler'),
            self::Realtime => __('Live updates'),
            self::ServerMonitor => __('Server monitor'),
            self::Storage => __('File storage'),
            self::Email => __('Email'),
            self::Programs => __('Programs'),
        };
    }

    /** What it does for the install, in a few words. */
    public function description(): string
    {
        return match ($this) {
            self::Database => __('PostgreSQL: every agent, chat and source'),
            self::Redis => __('Cache, sessions and the queue'),
            self::Queue => __('Laravel Horizon runs the background jobs'),
            self::Scheduler => __('Runs the tasks that repeat'),
            self::Realtime => __('Laravel Reverb sends live updates'),
            self::ServerMonitor => __('Laravel Pulse records how the app runs'),
            self::Storage => __('Uploads, exports and pictures'),
            self::Email => __('Sign-in links, invitations and alerts'),
            self::Programs => __('Read files and make PDFs'),
        };
    }

    public function icon(): Heroicon
    {
        return match ($this) {
            self::Database => Heroicon::OutlinedCircleStack,
            self::Redis => Heroicon::OutlinedBolt,
            self::Queue => Heroicon::OutlinedQueueList,
            self::Scheduler => Heroicon::OutlinedClock,
            self::Realtime => Heroicon::OutlinedSignal,
            self::ServerMonitor => Heroicon::OutlinedPresentationChartLine,
            self::Storage => Heroicon::OutlinedArchiveBox,
            self::Email => Heroicon::OutlinedEnvelope,
            self::Programs => Heroicon::OutlinedCommandLine,
        };
    }
}

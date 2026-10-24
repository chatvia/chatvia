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
    case ModelDetails = 'model_details';

    /** The product behind it, or what it is when it has no product of its own. */
    public function label(): string
    {
        return match ($this) {
            self::Database => __('PostgreSQL'),
            self::Redis => __('Redis'),
            self::Queue => __('Laravel Horizon'),
            self::Scheduler => __('Scheduler'),
            self::Realtime => __('Laravel Reverb'),
            self::ServerMonitor => __('Laravel Pulse'),
            self::Storage => __('File storage'),
            self::Email => __('Email'),
            self::Programs => __('Programs'),
            self::ModelDetails => 'models.dev',
        };
    }

    /** What it does for the install, in a few words. */
    public function description(): string
    {
        return match ($this) {
            self::Database => __('Data, vector and keyword search'),
            self::Redis => __('Chat memory and cache'),
            self::Queue => __('Background jobs'),
            self::Scheduler => __('Repeating tasks'),
            self::Realtime => __('Live updates'),
            self::ServerMonitor => __('Server monitor'),
            self::Storage => __('Uploads and exports'),
            self::Email => __('Outgoing email'),
            self::Programs => __('File readers and PDFs'),
            self::ModelDetails => __('What each model supports'),
        };
    }

    /** The site of a service named after one (models.dev), which its title links to. */
    public function url(): ?string
    {
        return match ($this) {
            self::ModelDetails => 'https://models.dev',
            default => null,
        };
    }

    /**
     * The logo of the product behind it (PostgreSQL, Redis, Laravel Horizon
     * and Pulse), shown in place of its icon; null for a service with no
     * product of its own.
     */
    public function logo(): ?string
    {
        return match ($this) {
            self::Database => asset('images/logos/services/postgresql.svg'),
            self::Redis => asset('images/logos/services/redis.svg'),
            self::Queue => asset('images/logos/tools/horizon.svg'),
            self::ServerMonitor => asset('images/logos/tools/pulse.svg'),
            default => null,
        };
    }

    public function icon(): Heroicon
    {
        return match ($this) {
            self::Database => Heroicon::OutlinedCircleStack,
            self::Redis => Heroicon::OutlinedBolt,
            self::Queue => Heroicon::OutlinedQueueList,
            self::Scheduler => Heroicon::Clock,
            self::Realtime => Heroicon::Signal,
            self::ServerMonitor => Heroicon::OutlinedPresentationChartLine,
            self::Storage => Heroicon::ArchiveBox,
            self::Email => Heroicon::Envelope,
            self::Programs => Heroicon::CommandLine,
            self::ModelDetails => Heroicon::OutlinedSparkles,
        };
    }
}

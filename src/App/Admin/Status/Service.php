<?php

declare(strict_types=1);

namespace App\Admin\Status;

use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Illuminate\Support\Uri;

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
            self::ModelDetails => __('Model catalog'),
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
            self::ModelDetails => __('What each model supports, from :site', ['site' => 'models.dev']),
        };
    }

    /**
     * A site the install reads from, not a part of it (models.dev): the
     * Status page lists it with the AI and other services.
     */
    public function isOutside(): bool
    {
        return $this === self::ModelDetails;
    }

    /**
     * The description, with the site it comes from (models.dev) linked and
     * opened in a new tab.
     */
    public function descriptionHtml(): HtmlString
    {
        $description = e($this->description());
        $url = $this->url();

        if ($url === null) {
            return new HtmlString($description);
        }

        $site = e(Uri::of($url)->host());

        return new HtmlString(Str::replace($site, '<a href="'.e($url).'" target="_blank" rel="noopener noreferrer" class="cv-service__link">'."{$site}</a>", $description));
    }

    /** The site of a service that comes from one (models.dev), which its description links to. */
    private function url(): ?string
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

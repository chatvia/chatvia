<?php

declare(strict_types=1);

use App\Admin\Clusters\Settings\Pages\ManageBackupSettings;
use App\Admin\Clusters\Settings\Pages\ManageKnowledgeSettings;
use App\Admin\Clusters\Settings\Pages\ManageMailSettings;
use App\Admin\Clusters\Settings\Pages\ManagePlatformSettings;
use App\Admin\Clusters\Settings\Pages\ManageStorageSettings;
use App\Admin\Issue\Types;
use App\Admin\Pages\License;
use App\Admin\Pages\Status;
use App\Admin\Resources\Workspaces\WorkspaceResource;
use Filament\Support\Icons\Heroicon;
use Support\Radar\ServiceKey;

/**
 * Radar, the admin panel's page of issues an admin can fix.
 *
 * `types`: every kind of issue (service trouble, failed jobs and tasks, a
 * part of the install that is down, workspaces at their limits), one class each under App\Admin\Issue\Types
 * (its key, title, advice and fix buttons). To add one, extend IssueType,
 * add its key to Support\Radar\IssueTypeKey and list it here. Code raises it
 * with Support\Radar\IssueRaised.
 *
 * `services`: the services that are not AI providers (every AI provider is
 * one already, with its own logo): their name, their logo (`logo`, a file
 * under public/) or else an icon (`icon`), the admin panel page where they
 * are set up, and their API hosts, so Radar knows a call to them.
 */
return [

    'types' => [
        Types\KeyRefused::class,
        Types\KeyMissing::class,
        Types\TurnedOff::class,
        Types\OutOfCredit::class,
        Types\ModelGone::class,
        Types\RateLimited::class,
        Types\ServiceDown::class,
        Types\Unreachable::class,
        Types\SendFailed::class,
        Types\BackupFailed::class,
        Types\LicenseInactive::class,
        Types\JobFailed::class,
        Types\QueueBacklog::class,
        Types\TaskFailed::class,
        Types\ServiceStopped::class,
        Types\MessageLimitReached::class,
        Types\GenerationLimitReached::class,
        Types\FreeLimitReached::class,
    ],

    'services' => [
        ServiceKey::FIRECRAWL => [
            'label' => 'Firecrawl',
            'logo' => 'images/logos/services/firecrawl.svg',
            'settings' => ManageKnowledgeSettings::class,
            'hosts' => ['api.firecrawl.dev'],
            'host_config' => ['services.firecrawl.endpoint'],
        ],
        ServiceKey::IP2LOCATION => [
            'label' => 'IP2Location',
            'icon' => Heroicon::OutlinedMapPin,
            'settings' => ManagePlatformSettings::class,
            'hosts' => ['api.ip2location.io'],
            'host_config' => ['services.ip2location.endpoint'],
        ],
        ServiceKey::MAIL => [
            'label' => 'Email',
            'icon' => Heroicon::OutlinedEnvelope,
            'settings' => ManageMailSettings::class,
        ],
        ServiceKey::BACKUPS => [
            'label' => 'Backups',
            'icon' => Heroicon::OutlinedCircleStack,
            'tone' => 'emerald',
            'settings' => ManageBackupSettings::class,
        ],
        ServiceKey::LICENSE => [
            'label' => 'License',
            'icon' => Heroicon::OutlinedKey,
            'settings' => License::class,
        ],
        ServiceKey::QUEUE => [
            'label' => 'The queue',
            'logo' => 'images/logos/tools/horizon.svg',
            'settings' => Status::class,
        ],
        ServiceKey::SCHEDULER => [
            'label' => 'The scheduler',
            'icon' => Heroicon::OutlinedClock,
            'settings' => Status::class,
        ],
        ServiceKey::DATABASE => [
            'label' => 'The database',
            'logo' => 'images/logos/services/postgresql.svg',
            'settings' => Status::class,
        ],
        ServiceKey::REDIS => [
            'label' => 'Redis',
            'logo' => 'images/logos/services/redis.svg',
            'settings' => Status::class,
        ],
        ServiceKey::REALTIME => [
            'label' => 'Reverb (live updates)',
            'icon' => Heroicon::OutlinedSignal,
            'settings' => Status::class,
        ],
        ServiceKey::STORAGE => [
            'label' => 'File storage',
            'icon' => Heroicon::OutlinedArchiveBox,
            'settings' => ManageStorageSettings::class,
        ],
        ServiceKey::WORKSPACES => [
            'label' => 'Workspaces',
            'icon' => Heroicon::OutlinedBuildingOffice2,
            'settings' => WorkspaceResource::class,
        ],
    ],

];

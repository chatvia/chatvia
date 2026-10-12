<?php

declare(strict_types=1);

namespace App\Admin\Clusters\Settings;

use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Support\Icons\Heroicon;

/**
 * The admin panel's Settings: one page per settings class
 * (App\Admin\Settings), listed in the cluster's side navigation.
 */
class SettingsCluster extends Cluster
{
    #[\Override]
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    #[\Override]
    protected static ?string $navigationLabel = 'Settings';

    #[\Override]
    protected static ?int $navigationSort = 100;

    #[\Override]
    protected static ?string $slug = 'settings';

    #[\Override]
    protected static ?string $clusterBreadcrumb = 'Settings';

    #[\Override]
    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Start;
}

<?php

declare(strict_types=1);

namespace App\Admin\Clusters\Settings\Pages;

use App\Admin\Clusters\Settings\AdminSettingsPage;
use App\Admin\Clusters\Settings\SettingsCard;
use App\Admin\Settings\BrandSettings;
use BackedEnum;
use Filament\Schemas\Components\Callout;
use Filament\Support\Icons\Heroicon;

/**
 * The "Powered by Chatvia" credit. Removing it is a paid option, so the page
 * says so next to the switch.
 */
class ManageBrandSettings extends AdminSettingsPage
{
    #[\Override]
    protected static string $settings = BrandSettings::class;

    #[\Override]
    protected static ?string $title = 'Brand';

    #[\Override]
    protected static ?string $slug = 'brand';

    #[\Override]
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSwatch;

    #[\Override]
    protected static ?int $navigationSort = 9;

    public static function description(): string
    {
        return 'The "Powered by Chatvia" credit.';
    }

    public static function overviewGroup(): string
    {
        return 'Brand and platform';
    }

    protected function cards(): array
    {
        return [
            SettingsCard::make('powered_by', 'Powered by', 'The credit under the hosted chat and widget of every agent.', Heroicon::OutlinedTag, fn (): array => [
                $this->toggle('powered_by_enabled', 'Show the credit'),
                Callout::make('Removing the credit is paid')
                    ->description('Removing the "Powered by Chatvia" attribution requires a payment of $399.')
                    ->icon(Heroicon::OutlinedExclamationTriangle)
                    ->warning(),
            ])->summary(fn (): string => $this->valueInUse('powered_by_enabled') ? 'Shown' : 'Hidden'),
        ];
    }
}

<?php

namespace App\Admin\Resources\Settings\Pages;

use App\Admin\Resources\Settings\SettingResource;
use App\Admin\Settings\AdminSetting;
use App\Admin\Settings\SettingCategory;
use App\Admin\Settings\SettingField;
use App\Admin\Settings\SettingGroup;
use App\Admin\Settings\SettingsStore;
use App\Admin\Settings\SettingStatus;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Size;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * The Settings overview: a tab per category, a card per settings group with
 * its status, and a link to the group's edit page.
 */
class ManageSettings extends Page
{
    #[\Override]
    protected static string $resource = SettingResource::class;

    #[\Override]
    protected static ?string $title = 'Settings';

    public function getSubheading(): string
    {
        return 'OAuth apps, API keys and webhook secrets for the services the app uses, and the workspace options.';
    }

    /**
     * The page is the resource's only list, so "Settings > Settings" adds nothing.
     */
    #[\Override]
    public function getBreadcrumbs(): array
    {
        return [];
    }

    public function content(Schema $schema): Schema
    {
        if (! SettingResource::canManage()) {
            return $schema->components([$this->twoFactorRequired()]);
        }

        $store = app(SettingsStore::class);

        /** @var Collection<string, AdminSetting> $settings */
        $settings = AdminSetting::query()
            ->whereIn('key', array_column(SettingGroup::cases(), 'value'))
            ->with('updatedBy')
            ->get()
            ->keyBy(fn (AdminSetting $setting): string => $setting->key->value);

        return $schema->components([
            Callout::make('Encrypted at rest')
                ->description('Every value is encrypted with the app key before it is saved. Changes apply right away, also to the queue workers.')
                ->icon(Heroicon::OutlinedLockClosed)
                ->info(),
            Tabs::make('Categories')
                ->vertical()
                ->persistTabInQueryString()
                ->tabs(array_map(
                    fn (SettingCategory $category): Tab => $this->tab($category, $settings, $store),
                    SettingCategory::cases(),
                )),
        ]);
    }

    private function twoFactorRequired(): Callout
    {
        return Callout::make('Set up two-factor authentication first')
            ->description('These settings hold API keys and secrets, so only admins who use an authenticator app can see or change them. Set it up on your profile, then come back.')
            ->icon(Heroicon::OutlinedShieldExclamation)
            ->warning()
            ->actions([
                Action::make('setUpTwoFactor')
                    ->label('Set up two-factor authentication')
                    ->icon(Heroicon::OutlinedDevicePhoneMobile)
                    ->url(Filament::getProfileUrl()),
            ]);
    }

    /**
     * @param  Collection<string, AdminSetting>  $settings
     */
    private function tab(SettingCategory $category, Collection $settings, SettingsStore $store): Tab
    {
        $groups = $category->groups();
        $usable = count(array_filter(
            $groups,
            fn (SettingGroup $group): bool => $store->status($group, $settings->get($group->value)) === SettingStatus::Configured,
        ));

        return Tab::make($category->label())
            ->icon($category->icon())
            ->badge($usable.' / '.count($groups))
            ->badgeColor($usable === count($groups) ? 'success' : 'gray')
            ->schema([
                Text::make($category->description())->color('gray'),
                Grid::make(['default' => 1, 'lg' => 2])
                    ->schema(array_map(
                        fn (SettingGroup $group): Section => $this->card($group, $settings->get($group->value), $store),
                        $groups,
                    )),
            ]);
    }

    private function card(SettingGroup $group, ?AdminSetting $setting, SettingsStore $store): Section
    {
        $status = $store->status($group, $setting);

        return Section::make($group->label())
            ->description($group->description())
            ->icon($group->icon())
            ->iconColor('primary')
            ->compact()
            ->afterHeader([
                Text::make($status->getLabel())
                    ->badge()
                    ->color($status->getColor())
                    ->icon($status->getIcon()),
            ])
            ->schema([
                Text::make($this->statusLine($group, $setting, $status, $store))
                    ->color('gray')
                    ->size(TextSize::ExtraSmall),
            ])
            ->footer([
                Action::make('configure'.Str::studly($group->value))
                    ->label($status === SettingStatus::Missing ? 'Set up' : 'Configure')
                    ->icon($status === SettingStatus::Missing ? Heroicon::OutlinedPlus : Heroicon::OutlinedPencilSquare)
                    ->color($status === SettingStatus::Missing ? 'primary' : 'gray')
                    ->size(Size::Small)
                    ->outlined()
                    ->url(SettingResource::getUrl('edit', ['record' => $group->value])),
            ]);
    }

    private function statusLine(SettingGroup $group, ?AdminSetting $setting, SettingStatus $status, SettingsStore $store): string
    {
        return match ($status) {
            SettingStatus::Configured => 'Saved '.$setting?->updated_at?->diffForHumans()
                .($setting?->updatedBy ? ' by '.$setting->updatedBy->name : ''),
            SettingStatus::Incomplete => 'Missing: '.collect($store->missingFields($group, $setting))
                ->map(fn (SettingField $field): string => $field->label)
                ->join(', ', ' and ').'.',
            SettingStatus::Missing => 'Not set up yet.',
        };
    }
}

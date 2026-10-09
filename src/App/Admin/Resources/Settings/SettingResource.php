<?php

namespace App\Admin\Resources\Settings;

use App\Admin\Resources\Settings\Pages\EditSetting;
use App\Admin\Resources\Settings\Pages\ManageSettings;
use App\Admin\Settings\AdminSetting;
use App\Admin\Settings\SettingsStore;
use App\Admin\StaffToolsAccess;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

/**
 * Third-party credentials: OAuth apps, API keys and webhook secrets. They are
 * stored encrypted in `admin_settings` and override the matching .env values
 * (see {@see SettingsStore}). The index shows one card per settings group;
 * each group has its own edit page.
 *
 * Every admin sees the Settings page, but only an admin who has set up
 * two-factor authentication can see or change the credentials: the same bar as
 * Horizon and Telescope ({@see StaffToolsAccess}). The others get a prompt to
 * set it up.
 */
class SettingResource extends Resource
{
    #[\Override]
    protected static ?string $model = AdminSetting::class;

    #[\Override]
    protected static ?string $slug = 'settings';

    #[\Override]
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    #[\Override]
    protected static ?string $navigationLabel = 'Settings';

    #[\Override]
    protected static ?int $navigationSort = 100;

    #[\Override]
    protected static ?string $modelLabel = 'setting';

    #[\Override]
    protected static ?string $pluralModelLabel = 'settings';

    #[\Override]
    protected static bool $isGloballySearchable = false;

    /**
     * The panel is admin-only already. The page stays in the navigation so an
     * admin without two-factor authentication learns why it is locked.
     */
    public static function canViewAny(): bool
    {
        return true;
    }

    public static function canEdit(Model $record): bool
    {
        return static::canManage();
    }

    /**
     * Whether the signed-in admin may see and change the credentials.
     */
    public static function canManage(): bool
    {
        return app(StaffToolsAccess::class)->allows();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function getRecordTitle(?Model $record): string
    {
        return $record instanceof AdminSetting ? $record->key->title() : 'Setting';
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageSettings::route('/'),
            'edit' => EditSetting::route('/{record}/edit'),
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Admin\Pages;

use App\Admin\AdminPanelProvider;
use App\Admin\StaffTool;
use App\Admin\StaffToolsAccess;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Tools: a card for each staff tool that watches the install from outside
 * the panel (the queue, the app's health), with what it is for and a link
 * that opens it in a new tab. A tool this install lacks, or that the admin
 * may not open, shows as unavailable, without a link ({@see StaffTool}).
 *
 * It sits above Settings in the sidebar, and opens for the same staff as
 * the tools themselves ({@see StaffToolsAccess}).
 */
class Tools extends Page
{
    #[\Override]
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWrenchScrewdriver;

    #[\Override]
    protected static string|UnitEnum|null $navigationGroup = AdminPanelProvider::SYSTEM_NAVIGATION_GROUP;

    #[\Override]
    protected static ?int $navigationSort = 97;

    #[\Override]
    protected static ?string $slug = 'tools';

    #[\Override]
    public static function canAccess(): bool
    {
        return app(StaffToolsAccess::class)->allows();
    }

    #[\Override]
    public static function getNavigationLabel(): string
    {
        return __('Tools');
    }

    #[\Override]
    public function getTitle(): string
    {
        return __('Tools');
    }

    public function getSubheading(): string
    {
        return __('Tools that watch this install from outside the panel. Each one opens in a new tab.');
    }

    #[\Override]
    public function content(Schema $schema): Schema
    {
        return $schema->components([
            View::make('admin.tools')->viewData(fn (): array => ['tools' => StaffTool::cases()]),
        ]);
    }
}

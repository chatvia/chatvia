<?php

declare(strict_types=1);

namespace App\Admin\Clusters\Settings\Pages;

use App\Admin\Clusters\Settings\AdminSettingsPage;
use App\Admin\Clusters\Settings\SettingsCluster;
use App\Admin\StaffToolsAccess;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * The start of the admin panel's Settings: a tile for each Settings page, in
 * groups, with its icon, what it holds and how much of it is set up. A tile
 * opens its page.
 */
class SettingsOverview extends Page
{
    /** The groups of tiles, in order. */
    private const array GROUPS = ['Sign-in and people', 'AI and knowledge', 'Connections', 'Brand and platform'];

    #[\Override]
    protected static ?string $cluster = SettingsCluster::class;

    #[\Override]
    protected static ?string $title = 'Settings';

    #[\Override]
    protected static ?string $navigationLabel = 'Overview';

    #[\Override]
    protected static ?string $slug = 'overview';

    #[\Override]
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    #[\Override]
    protected static ?int $navigationSort = 0;

    public function getSubheading(): string
    {
        return 'Everything you can change about this install. Changes apply right away.';
    }

    /**
     * The overview is the Settings title already, so no "Settings > Settings".
     */
    #[\Override]
    public function getBreadcrumbs(): array
    {
        return [];
    }

    /**
     * @return array<mixed>
     */
    #[\Override]
    public function getSubNavigation(): array
    {
        return [];
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('setup')
                ->label('Run the setup again')
                ->icon(Heroicon::OutlinedRocketLaunch)
                ->color('gray')
                ->url(route('filament.admin.setup'))
                ->visible(fn (): bool => app(StaffToolsAccess::class)->allows()),
        ];
    }

    #[\Override]
    public function content(Schema $schema): Schema
    {
        $canManage = app(StaffToolsAccess::class)->allows();

        return $schema->components([
            ...($canManage ? [] : [AdminSettingsPage::twoFactorRequired()]),
            View::make('filament.settings.tiles')->viewData(fn (): array => ['groups' => $this->groups($canManage)]),
        ]);
    }

    /**
     * @return list<array{heading: string, tiles: list<array<string, mixed>>}>
     */
    private function groups(bool $canManage): array
    {
        $pages = collect(SettingsCluster::getClusteredComponents())
            ->filter(fn (string $component): bool => is_subclass_of($component, AdminSettingsPage::class))
            ->sortBy(fn (string $page): int => $page::getNavigationSort() ?? 0);

        return collect(self::GROUPS)
            ->map(fn (string $heading): array => [
                'heading' => $heading,
                'tiles' => $pages
                    ->filter(fn (string $page): bool => $page::overviewGroup() === $heading)
                    ->map(fn (string $page): array => $this->tile($page, $canManage))
                    ->values()
                    ->all(),
            ])
            ->filter(fn (array $group): bool => $group['tiles'] !== [])
            ->values()
            ->all();
    }

    /**
     * @param  class-string<AdminSettingsPage>  $page
     * @return array<string, mixed>
     */
    private function tile(string $page, bool $canManage): array
    {
        [$status, $tone] = $canManage ? app($page)->overviewStatus() : [null, 'gray'];

        return [
            'key' => $page::getSlug(),
            'title' => (string) $page::getNavigationLabel(),
            'description' => $page::description(),
            'icon' => $page::getNavigationIcon(),
            'status' => $status,
            'tone' => $tone,
            'href' => $page::getUrl(),
        ];
    }
}

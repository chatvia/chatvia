<?php

declare(strict_types=1);

namespace App\Admin\Clusters\Settings\Pages;

use App\Admin\Clusters\Settings\AdminSettingsPage;
use App\Admin\Clusters\Settings\SettingsCluster;
use App\Admin\Clusters\Settings\SettingsSearch;
use App\Admin\Settings\CreditLicense;
use App\Admin\Settings\SetupRequirements;
use App\Admin\Settings\SetupStatus;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\Url;

/**
 * The start of the admin panel's Settings: a tile for each Settings page, in
 * groups, with its icon, what it holds and how much of it is set up. A tile
 * opens its page.
 *
 * A search box above the tiles searches all of Settings, the sections of
 * each page and their options too ({@see SettingsSearch}); while it holds a
 * question, its finds take the place of the tiles. The question stays in the
 * address (`?q=`), so Back from a find returns to the list.
 */
class SettingsOverview extends Page
{
    /** The groups of tiles, in order. The Settings search lists its finds in this order too. */
    public const array GROUPS = ['Access and security', 'AI and knowledge', 'Connections', 'Platform and brand'];

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

    /** What is typed in the search box. */
    #[Url(as: 'q', except: '')]
    public string $search = '';

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
            Action::make('finishSetup')
                ->label('Finish the setup')
                ->icon(Heroicon::OutlinedRocketLaunch)
                ->color('warning')
                ->visible(fn (): bool => SetupStatus::current() === SetupStatus::Skipped)
                ->tooltip(fn (): ?string => ($missing = app(SetupRequirements::class)->missing()) === [] ? null : 'Still missing: '.implode(', ', $missing).'.')
                ->url(route('filament.admin.setup')),
        ];
    }

    #[\Override]
    public function content(Schema $schema): Schema
    {
        return $schema->components([
            $this->supportCallout(),
            View::make('filament.settings.tiles')->viewData(function (): array {
                $search = app(SettingsSearch::class);
                $terms = $search->terms($this->search);

                return [
                    'groups' => $terms === [] ? $this->groups() : [],
                    'search' => $search,
                    'query' => $this->search,
                    'terms' => $terms,
                    'hits' => $search->search($this->search),
                ];
            }),
        ]);
    }

    /**
     * Asks the install to buy a license, which supports the developer and
     * hides the "Powered by ChatVia.ai" credit; thanks the install that has one.
     */
    private function supportCallout(): Callout
    {
        $license = app(CreditLicense::class);
        $key = config('chatvia.license.key');
        $key = filled($key) ? $key : null;

        if ($license->allowsHidingCredit($key)) {
            return Callout::make('Thank you for your support')
                ->description('Your license is active, licensed to '.$license->read($key)?->licensee.'. It helps me keep ChatVia.ai free and open source.')
                ->icon(Heroicon::OutlinedHeart)
                ->success()
                ->actions([$this->hireMeAction()]);
        }

        return Callout::make('Support ChatVia.ai')
            ->description('ChatVia.ai is free and open source. A license costs $399, one time. It supports my work on ChatVia.ai and removes the "Powered by ChatVia.ai" credit from your chats.')
            ->icon(Heroicon::OutlinedHeart)
            ->color('primary')
            ->extraAttributes(['class' => 'cv-link-dividers'])
            ->actions([
                Action::make('buyLicense')
                    ->label('Buy a license')
                    ->url((string) config('chatvia.license.buy_url'))
                    ->openUrlInNewTab(),
                Action::make('addLicenseKey')
                    ->label('I have a key')
                    ->url(ManageBrandSettings::getUrl())
                    ->link(),
                $this->hireMeAction(),
            ]);
    }

    /** The developer's services (also on Settings → Branding). */
    private function hireMeAction(): Action
    {
        return Action::make('hireMe')
            ->label('Hire me')
            ->url('https://mayahi.net/services')
            ->openUrlInNewTab()
            ->link();
    }

    /**
     * @return list<array{heading: string, tiles: list<array<string, mixed>>}>
     */
    private function groups(): array
    {
        $pages = collect(SettingsCluster::getClusteredComponents())
            ->filter(fn (string $component): bool => is_subclass_of($component, AdminSettingsPage::class))
            ->sortBy(fn (string $page): int => $page::getNavigationSort() ?? 0);

        return collect(self::GROUPS)
            ->map(fn (string $heading): array => [
                'heading' => $heading,
                'tiles' => $pages
                    ->filter(fn (string $page): bool => $page::overviewGroup() === $heading)
                    ->map(fn (string $page): array => $this->tile($page))
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
    private function tile(string $page): array
    {
        [$status, $tone] = app($page)->overviewStatus();

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

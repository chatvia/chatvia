<?php

declare(strict_types=1);

namespace App\Admin\Pages;

use App\Admin\Widgets\ChannelsChart;
use App\Admin\Widgets\GrowthChart;
use App\Admin\Widgets\MessagesChart;
use App\Admin\Widgets\PlatformStatsOverview;
use App\Admin\Widgets\TopAgentsTable;
use App\Admin\Widgets\TopWorkspacesTable;
use App\Identity\Workspace\Models\Workspace;
use Domain\Analytics\Enums\AnalyticsPeriod;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

/**
 * The admin panel's home: activity across every workspace on the install —
 * messages, conversations, sign-ups, agents, leads and the busiest
 * workspaces and agents — for a chosen period, or for one workspace.
 *
 * The filters live in the URL and the session, so a reload or a shared link
 * keeps them. Widgets that only make sense across workspaces (sign-ups, top
 * workspaces) are left out while one workspace is chosen.
 */
class Dashboard extends BaseDashboard
{
    use HasFiltersForm;

    /** How many workspaces the workspace filter lists per search. */
    private const int WORKSPACE_SEARCH_LIMIT = 50;

    public function getSubheading(): string
    {
        $scope = DashboardFilters::scope($this->filters);
        $workspace = $scope->isWholePlatform() ? null : Workspace::query()->whereKey($scope->workspaceId)->value('name');

        return $workspace === null
            ? "Activity across every workspace, {$scope->period->label}."
            : "Activity in {$workspace}, {$scope->period->label}.";
    }

    public function filtersForm(Schema $schema): Schema
    {
        $isCustom = fn (Get $get): bool => $get(DashboardFilters::PERIOD) === DashboardFilters::CUSTOM;

        return $schema->components([
            Section::make()
                ->columnSpanFull()
                ->columns(['md' => 2, 'xl' => 4])
                ->schema([
                    Select::make(DashboardFilters::PERIOD)
                        ->label('Period')
                        ->options(DashboardFilters::periodOptions())
                        ->default(AnalyticsPeriod::ThisMonth->value)
                        ->selectablePlaceholder(false)
                        ->native(false),
                    DatePicker::make(DashboardFilters::START_DATE)
                        ->label('From')
                        ->maxDate(today())
                        ->visible($isCustom),
                    DatePicker::make(DashboardFilters::END_DATE)
                        ->label('To')
                        ->maxDate(today())
                        ->visible($isCustom),
                    Select::make(DashboardFilters::WORKSPACE)
                        ->label('Workspace')
                        ->placeholder('All workspaces')
                        ->searchable()
                        ->getSearchResultsUsing(fn (string $search): array => $this->searchWorkspaces($search))
                        ->getOptionLabelUsing(fn (mixed $value): ?string => is_numeric($value) ? $this->workspaceLabel((int) $value) : null),
                ]),
        ]);
    }

    /**
     * @return list<class-string>
     */
    public function getWidgets(): array
    {
        $wholePlatform = DashboardFilters::workspaceId($this->filters) === null;

        return array_values(array_filter([
            PlatformStatsOverview::class,
            MessagesChart::class,
            ChannelsChart::class,
            $wholePlatform ? GrowthChart::class : null,
            $wholePlatform ? TopWorkspacesTable::class : null,
            TopAgentsTable::class,
        ]));
    }

    /**
     * Workspaces whose name or owner email matches, by name.
     *
     * @return array<int, string>
     */
    private function searchWorkspaces(string $search): array
    {
        return $this->workspaces()
            ->where(fn (Builder $query): Builder => $query
                ->whereLike('name', "%{$search}%")
                ->orWhereHas('owner', fn (Builder $owner): Builder => $owner->whereLike('email', "%{$search}%")))
            ->orderBy('name')
            ->limit(self::WORKSPACE_SEARCH_LIMIT)
            ->get()
            ->mapWithKeys(fn (Workspace $workspace): array => [$workspace->id => $this->label($workspace)])
            ->all();
    }

    private function workspaceLabel(?int $id): ?string
    {
        $workspace = $this->workspaces()->find($id);

        return $workspace instanceof Workspace ? $this->label($workspace) : null;
    }

    private function label(Workspace $workspace): string
    {
        $email = $workspace->owner?->email;

        return filled($email) ? "{$workspace->name} ({$email})" : $workspace->name;
    }

    /**
     * Every workspace, with its owner for the label.
     *
     * @return Builder<Workspace>
     */
    private function workspaces(): Builder
    {
        return Workspace::query()->with('owner');
    }
}

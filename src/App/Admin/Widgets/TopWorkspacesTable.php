<?php

declare(strict_types=1);

namespace App\Admin\Widgets;

use App\Admin\Pages\DashboardFilters;
use App\Admin\Resources\Workspaces\WorkspaceResource;
use Domain\Analytics\Data\Platform\TopWorkspaceData;
use Domain\Analytics\Queries\Platform\TopWorkspacesQuery;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget;

/**
 * The ten workspaces with the most messages in the filtered period. A row
 * opens the workspace; an admin's own workspace is not on the Workspaces
 * page, so its row does not link. Shown only for the whole platform.
 */
class TopWorkspacesTable extends TableWidget
{
    use InteractsWithPageFilters;

    #[\Override]
    protected static ?int $sort = 5;

    #[\Override]
    protected static ?string $heading = 'Busiest workspaces';

    #[\Override]
    public function table(Table $table): Table
    {
        return $table
            ->records(fn (): array => collect(app(TopWorkspacesQuery::class)->get(DashboardFilters::scope($this->pageFilters)))
                ->mapWithKeys(fn (TopWorkspaceData $workspace): array => [$workspace->workspaceId => [
                    'id' => $workspace->workspaceId,
                    'name' => $workspace->name ?? 'Deleted workspace',
                    'email' => $workspace->email,
                    'messages' => $workspace->messages,
                    'conversations' => $workspace->conversations,
                    'agents' => $workspace->agents,
                    'linked' => $workspace->name !== null,
                ]])
                ->all())
            ->columns([
                TextColumn::make('name')
                    ->label('Workspace')
                    ->weight('medium')
                    ->description(fn (array $record): ?string => $record['email']),
                TextColumn::make('messages')
                    ->numeric(),
                TextColumn::make('conversations')
                    ->numeric(),
                TextColumn::make('agents')
                    ->numeric(),
            ])
            ->recordUrl(fn (array $record): ?string => $record['linked']
                ? WorkspaceResource::getUrl('view', ['record' => $record['id']])
                : null)
            ->paginated(false)
            ->emptyStateIcon(Heroicon::OutlinedBuildingOffice2)
            ->emptyStateHeading('No messages in this period');
    }
}

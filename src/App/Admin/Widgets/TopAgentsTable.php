<?php

declare(strict_types=1);

namespace App\Admin\Widgets;

use App\Admin\Pages\DashboardFilters;
use Domain\Analytics\Data\Platform\TopAgentData;
use Domain\Analytics\Queries\Platform\TopAgentsQuery;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget;

/**
 * The ten agents with the most messages in the filtered period, with the
 * workspace each belongs to (left out while one workspace is chosen).
 */
class TopAgentsTable extends TableWidget
{
    use InteractsWithPageFilters;

    #[\Override]
    protected static ?int $sort = 6;

    #[\Override]
    protected static ?string $heading = 'Busiest agents';

    #[\Override]
    public function table(Table $table): Table
    {
        $wholePlatform = DashboardFilters::workspaceId($this->pageFilters) === null;

        return $table
            ->records(fn (): array => collect(app(TopAgentsQuery::class)->get(DashboardFilters::scope($this->pageFilters)))
                ->mapWithKeys(fn (TopAgentData $agent): array => [$agent->agentId => [
                    'name' => $agent->name ?? 'Deleted agent',
                    'workspace' => $agent->workspaceName ?? 'Deleted workspace',
                    'messages' => $agent->messages,
                    'conversations' => $agent->conversations,
                ]])
                ->all())
            ->columns([
                TextColumn::make('name')
                    ->label('Agent')
                    ->weight('medium')
                    ->description(fn (array $record): ?string => $wholePlatform ? $record['workspace'] : null),
                TextColumn::make('messages')
                    ->numeric(),
                TextColumn::make('conversations')
                    ->numeric(),
            ])
            ->paginated(false)
            ->emptyStateIcon(Heroicon::OutlinedCpuChip)
            ->emptyStateHeading('No messages in this period');
    }
}

<?php

declare(strict_types=1);

namespace App\Admin\Resources\Issues\RelationManagers;

use App\Admin\Issue\Enums\IssueActorKind;
use App\Admin\Issue\Models\Issue;
use App\Admin\Issue\Models\IssueOccurrence;
use App\Admin\Resources\Issues\IssueResource;
use App\Admin\Resources\Workspaces\WorkspaceResource;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Every time an issue happened, newest first: when, who was behind it,
 * the workspace and agent, what the app was doing and what the service
 * said. A row opens all its details in a panel. Read-only.
 */
class OccurrencesRelationManager extends RelationManager
{
    #[\Override]
    protected static string $relationship = 'occurrences';

    #[\Override]
    protected static ?string $title = 'Occurrences';

    #[\Override]
    protected static string|BackedEnum|null $icon = Heroicon::OutlinedQueueList;

    /** The admin panel is staff-only; the dashboard's policies do not apply. */
    #[\Override]
    protected static bool $shouldSkipAuthorization = true;

    #[\Override]
    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        return $ownerRecord instanceof Issue ? (string) $ownerRecord->occurrences()->count() : null;
    }

    #[\Override]
    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['user', 'chatter', 'apiKey', 'workspace', 'agent']))
            ->columns([
                TextColumn::make('occurred_at')
                    ->label('When')
                    ->since()
                    ->dateTimeTooltip()
                    ->sortable(),
                TextColumn::make('actor_kind')
                    ->label('Who')
                    ->formatStateUsing(fn (IssueOccurrence $record): string => $record->actorLabel())
                    ->icon(fn (IssueActorKind $state): Heroicon => match ($state) {
                        IssueActorKind::Staff => Heroicon::OutlinedUserCircle,
                        IssueActorKind::Chatter => Heroicon::OutlinedChatBubbleLeftRight,
                        IssueActorKind::ApiKey => Heroicon::OutlinedKey,
                        IssueActorKind::Visitor => Heroicon::OutlinedGlobeAlt,
                        IssueActorKind::System => Heroicon::OutlinedCog6Tooth,
                    }),
                TextColumn::make('workspace.name')
                    ->label('Workspace')
                    ->url(fn (IssueOccurrence $record): ?string => $record->workspace !== null ? WorkspaceResource::getUrl('view', ['record' => $record->workspace]) : null)
                    ->color('primary')
                    ->placeholder('—'),
                TextColumn::make('agent.name')
                    ->label('Agent')
                    ->placeholder('—'),
                TextColumn::make('origin_name')
                    ->label('What the app was doing')
                    ->formatStateUsing(fn (IssueOccurrence $record): string => $record->originLabel())
                    ->tooltip(fn (IssueOccurrence $record): ?string => $record->origin_name)
                    ->placeholder('—'),
                TextColumn::make('message')
                    ->limit(80)
                    ->tooltip(fn (IssueOccurrence $record): ?string => $record->message)
                    ->placeholder('—')
                    ->wrap(),
            ])
            ->filters([
                SelectFilter::make('actor_kind')
                    ->label('Who')
                    ->options(collect(IssueActorKind::cases())->mapWithKeys(fn (IssueActorKind $kind): array => [$kind->value => $kind->label()])->all()),
                SelectFilter::make('workspace')
                    ->relationship('workspace', 'name')
                    ->searchable(),
            ])
            ->defaultSort('occurred_at', 'desc')
            ->recordActions([
                ViewAction::make()
                    ->slideOver()
                    ->modalHeading('Occurrence')
                    ->schema(fn (IssueOccurrence $record): array => IssueResource::occurrenceEntries($record)),
            ]);
    }
}

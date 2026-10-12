<?php

namespace App\Admin\Resources\Workspaces;

use App\Admin\Resources\Workspaces\Pages\ListWorkspaces;
use App\Admin\Resources\Workspaces\Pages\ViewWorkspace;
use App\Identity\Workspace\Models\Workspace;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Oversight of customer workspaces (the `workspaces` table) with their owner.
 * Read-only apart from the monthly message limit set on the view page.
 * Staff-only; the panel itself is gated to admins.
 */
class WorkspaceResource extends Resource
{
    #[\Override]
    protected static ?string $model = Workspace::class;

    #[\Override]
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    #[\Override]
    protected static ?string $recordTitleAttribute = 'name';

    #[\Override]
    protected static ?string $modelLabel = 'workspace';

    #[\Override]
    protected static ?string $pluralModelLabel = 'workspaces';

    /**
     * Eager-load the owner, the members count and the message limit so the
     * columns cost no query per row.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['owner', 'messageLimit'])
            ->withCount('members');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('owner.email')
                    ->label('Owner email')
                    ->searchable(),
                TextColumn::make('owner.registered_country_code')
                    ->label('Country')
                    ->placeholder('—'),
                TextColumn::make('members_count')
                    ->label('Members')
                    ->badge()
                    ->sortable(),
                TextColumn::make('messageLimit.monthly_limit')
                    ->label('Message limit')
                    ->numeric()
                    ->placeholder('No limit'),
                TextColumn::make('status')
                    ->badge()
                    ->state(fn (Workspace $record): string => $record->owner?->isDeactivated() ? 'Deactivated' : 'Active')
                    ->color(fn (string $state): string => $state === 'Active' ? 'success' : 'danger'),
                TextColumn::make('owner.last_login_at')
                    ->label('Owner last login')
                    ->since()
                    ->placeholder('Never'),
                TextColumn::make('created_at')
                    ->label('Joined')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                TernaryFilter::make('status')
                    ->label('Status')
                    ->placeholder('All workspaces')
                    ->trueLabel('Deactivated only')
                    ->falseLabel('Active only')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereHas('owner', fn (Builder $owner): Builder => $owner->whereNotNull('deactivated_at')),
                        false: fn (Builder $query): Builder => $query->whereHas('owner', fn (Builder $owner): Builder => $owner->whereNull('deactivated_at')),
                        blank: fn (Builder $query): Builder => $query,
                    ),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                ViewAction::make(),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Account')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('name'),
                        TextEntry::make('email')
                            ->label('Email address')
                            ->placeholder('—'),
                        TextEntry::make('status')
                            ->badge()
                            ->state(fn (Workspace $record): string => $record->owner?->isDeactivated() ? 'Deactivated' : 'Active')
                            ->color(fn (string $state): string => $state === 'Active' ? 'success' : 'danger'),
                        TextEntry::make('deactivation_reason')
                            ->placeholder('—'),
                        TextEntry::make('locale')
                            ->placeholder('—'),
                    ]),
                Section::make('Activity')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('owner.last_login_at')
                            ->label('Owner last login')
                            ->dateTime()
                            ->placeholder('Never'),
                        TextEntry::make('created_at')
                            ->label('Joined')
                            ->dateTime(),
                        TextEntry::make('owner.registered_country_code')
                            ->label('Country')
                            ->placeholder('—'),
                        TextEntry::make('owner.registered_ip')
                            ->label('Registered IP')
                            ->placeholder('—'),
                        TextEntry::make('members_count')
                            ->label('Members'),
                    ]),
                Section::make('Messages')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('messageLimit.monthly_limit')
                            ->label('Monthly limit on agent replies')
                            ->numeric()
                            ->placeholder('No limit'),
                        TextEntry::make('messages_used')
                            ->label('Used this month')
                            ->state(fn (Workspace $record): ?int => $record->messageLimit?->usedThisMonth())
                            ->numeric()
                            ->placeholder('Counted only while a limit is set'),
                    ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWorkspaces::route('/'),
            'view' => ViewWorkspace::route('/{record}'),
        ];
    }
}

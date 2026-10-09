<?php

namespace App\Admin\Resources\Workspaces;

use App\Admin\Resources\Workspaces\Pages\ListWorkspaces;
use App\Admin\Resources\Workspaces\Pages\ViewWorkspace;
use App\Identity\Workspace\Enums\UserType;
use App\Identity\Workspace\Models\User;
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
 * Read-only oversight of customer accounts — the `users` rows with
 * `type = workspace` (account owners who sign up, manage a team and pay).
 * Staff-only; the panel itself is gated to admins.
 */
class WorkspaceResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $modelLabel = 'workspace';

    protected static ?string $pluralModelLabel = 'workspaces';

    /**
     * Restrict every query (list + record binding) to workspace owners, and
     * eager-count their members so the table column is a single aggregate.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('type', UserType::Workspace)
            ->withCount('users');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('email')
                    ->label('Email address')
                    ->searchable(),
                TextColumn::make('registered_country_code')
                    ->label('Country')
                    ->placeholder('—'),
                TextColumn::make('users_count')
                    ->label('Members')
                    ->badge()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->state(fn (User $record): string => $record->isDeactivated() ? 'Deactivated' : 'Active')
                    ->color(fn (string $state): string => $state === 'Active' ? 'success' : 'danger'),
                TextColumn::make('last_login_at')
                    ->label('Last login')
                    ->since()
                    ->sortable()
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
                        true: fn (Builder $query): Builder => $query->whereNotNull('deactivated_at'),
                        false: fn (Builder $query): Builder => $query->whereNull('deactivated_at'),
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
                            ->state(fn (User $record): string => $record->isDeactivated() ? 'Deactivated' : 'Active')
                            ->color(fn (string $state): string => $state === 'Active' ? 'success' : 'danger'),
                        TextEntry::make('deactivation_reason')
                            ->placeholder('—'),
                        TextEntry::make('domain')
                            ->placeholder('—'),
                        TextEntry::make('locale')
                            ->placeholder('—'),
                    ]),
                Section::make('Activity')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('last_login_at')
                            ->label('Last login')
                            ->dateTime()
                            ->placeholder('Never'),
                        TextEntry::make('created_at')
                            ->label('Joined')
                            ->dateTime(),
                        TextEntry::make('registered_country_code')
                            ->label('Country')
                            ->placeholder('—'),
                        TextEntry::make('registered_ip')
                            ->label('Registered IP')
                            ->placeholder('—'),
                        TextEntry::make('users_count')
                            ->label('Members')
                            ->state(fn (User $record): int => $record->workspaceUsersCount()),
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

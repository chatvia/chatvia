<?php

namespace App\Admin\Resources\Workspaces;

use App\Admin\Resources\Workspaces\Pages\ListWorkspaces;
use App\Admin\Resources\Workspaces\Pages\ViewWorkspace;
use App\Admin\Resources\Workspaces\RelationManagers\AgentsRelationManager;
use App\Admin\Resources\Workspaces\RelationManagers\TeammatesRelationManager;
use App\Identity\Workspace\Models\Workspace;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Support\Localization\Country\Models\Country;

/**
 * Oversight of customer workspaces (the `workspaces` table) with their owner.
 * The view page is the workspace's dashboard: its numbers, agents and
 * people, and what staff may do to it (edit, password reset, message limit,
 * deactivate, delete).
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
                    ->state(fn (Workspace $record): string => WorkspaceStatus::for($record)->label())
                    ->color(fn (Workspace $record): string => WorkspaceStatus::for($record)->color()),
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

    /**
     * The Overview tab of a workspace's page: who owns it, who works in it
     * and its message limit, as Settings-style cards. Agents and teammates
     * have their own tabs ({@see self::getRelations()}).
     */
    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make('Account')
                    ->description('The workspace and the person who owns it.')
                    ->icon(Heroicon::OutlinedBuildingOffice2)
                    ->extraAttributes(['class' => 'cv-settings-card'])
                    ->afterHeader([
                        Text::make(fn (Workspace $record): string => WorkspaceStatus::for($record)->label())
                            ->badge()
                            ->size(TextSize::Small)
                            ->color(fn (Workspace $record): string => WorkspaceStatus::for($record)->color()),
                    ])
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('name')
                            ->label('Workspace name'),
                        TextEntry::make('owner.name')
                            ->label('Owner')
                            ->placeholder('No owner'),
                        TextEntry::make('owner.email')
                            ->label('Owner email')
                            ->copyable()
                            ->placeholder('—'),
                        TextEntry::make('owner.deactivation_reason')
                            ->label('Why it is switched off')
                            ->placeholder('No reason given')
                            ->visible(fn (Workspace $record): bool => WorkspaceStatus::for($record) !== WorkspaceStatus::Active),
                        TextEntry::make('owner.locale')
                            ->label('Language')
                            ->formatStateUsing(fn (string $state): string => config("chatvia.languages.{$state}") ?? $state)
                            ->placeholder('—'),
                        TextEntry::make('owner.registered_country_code')
                            ->label('Country')
                            ->formatStateUsing(fn (string $state): string => self::countryName($state))
                            ->placeholder('—'),
                        TextEntry::make('owner.registered_ip')
                            ->label('Registered IP')
                            ->copyable()
                            ->placeholder('—'),
                        TextEntry::make('created_at')
                            ->label('Joined')
                            ->dateTime(),
                        TextEntry::make('owner.last_login_at')
                            ->label('Owner last sign-in')
                            ->since()
                            ->dateTimeTooltip()
                            ->placeholder('Never'),
                        TextEntry::make('two_step')
                            ->label('Owner two-step sign-in')
                            ->badge()
                            ->state(fn (Workspace $record): string => $record->owner?->usesTwoFactor() ? 'On' : 'Off')
                            ->color(fn (string $state): string => $state === 'On' ? 'success' : 'gray'),
                        TextEntry::make('spectra_access')
                            ->label('Spectra access')
                            ->badge()
                            ->state(fn (Workspace $record): string => $record->spectra_access ? 'Allowed' : 'Not allowed')
                            ->color(fn (string $state): string => $state === 'Allowed' ? 'warning' : 'gray')
                            ->tooltip('Whether the workspace\'s staff may open Spectra, which shows every workspace\'s AI calls.'),
                    ]),
                Section::make('People')
                    ->description('Who works in the workspace and who chats with its agents.')
                    ->icon(Heroicon::OutlinedUsers)
                    ->extraAttributes(['class' => 'cv-settings-card'])
                    ->columns(3)
                    ->schema([
                        TextEntry::make('teammates_count')
                            ->label('Teammates')
                            ->state(fn (Workspace $record): int => $record->teammates()->count())
                            ->numeric(),
                        TextEntry::make('teams_count')
                            ->label('Teams')
                            ->state(fn (Workspace $record): int => $record->teams()->count())
                            ->numeric(),
                        TextEntry::make('chatters_count')
                            ->label('Chatters')
                            ->state(fn (Workspace $record): int => $record->chatters()->count())
                            ->numeric(),
                    ]),
                Section::make('Message limit')
                    ->description('A cap on the agent replies of the whole workspace each month.')
                    ->icon(Heroicon::OutlinedAdjustmentsHorizontal)
                    ->extraAttributes(['class' => 'cv-settings-card'])
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
                        TextEntry::make('limit_restarts_on')
                            ->label('The count restarts on')
                            ->state(fn (): string => now()->addMonthNoOverflow()->startOfMonth()->format('j F Y'))
                            ->visible(fn (Workspace $record): bool => $record->messageLimit !== null),
                    ]),
            ]);
    }

    /**
     * @return list<class-string<RelationManager>>
     */
    public static function getRelations(): array
    {
        return [
            AgentsRelationManager::class,
            TeammatesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWorkspaces::route('/'),
            'view' => ViewWorkspace::route('/{record}'),
        ];
    }

    /**
     * "Germany (DE)", named by ICU; the bare code when it is unknown. Codes
     * are stored lowercase, like the `countries` table's.
     */
    private static function countryName(string $code): string
    {
        $country = Country::query()->where('code', strtolower($code))->first();

        return $country instanceof Country ? $country->localizedName().' ('.strtoupper($code).')' : strtoupper($code);
    }
}

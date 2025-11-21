<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Form;
use Filament\Infolists\Components\Section as InfolistSection;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'User Management';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Basic Information')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->required()
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('email')
                                    ->email()
                                    ->required()
                                    ->maxLength(255),
                            ]),
                        Grid::make(2)
                            ->schema([
                                Forms\Components\DateTimePicker::make('email_verified_at'),
                                Forms\Components\TextInput::make('password')
                                    ->password()
                                    ->dehydrated(fn ($state) => filled($state))
                                    ->required(fn (string $context): bool => $context === 'create')
                                    ->maxLength(255)
                                    ->placeholder(fn (string $context): string => $context === 'edit' ? 'Leave blank to keep current password' : ''),
                            ]),
                    ]),

                Section::make('Limits & Quotas')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                Forms\Components\TextInput::make('quota.agents_limit')
                                    ->label('Agents Limit')
                                    ->numeric(),
                                Forms\Components\TextInput::make('quota.pages_limit')
                                    ->label('Pages Limit')
                                    ->numeric(),
                                Forms\Components\TextInput::make('quota.links_limit')
                                    ->label('Links Limit')
                                    ->numeric(),
                            ]),
                        Grid::make(3)
                            ->schema([
                                Forms\Components\TextInput::make('quota.minutes_limit')
                                    ->label('Minutes Limit')
                                    ->numeric(),
                                Forms\Components\TextInput::make('quota.custom_domains_limit')
                                    ->label('Custom Domains Limit')
                                    ->numeric(),
                                Forms\Components\TextInput::make('quota.members_limit')
                                    ->label('Members Limit')
                                    ->numeric(),
                            ]),
                    ]),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                InfolistSection::make('Basic Information')
                    ->schema([
                        TextEntry::make('name'),
                        TextEntry::make('email'),
                        TextEntry::make('email_verified_at')
                            ->dateTime()
                            ->placeholder('Not verified'),
                        TextEntry::make('created_at')
                            ->dateTime()
                            ->label('Member Since'),
                        TextEntry::make('registered_ip')
                            ->label('Registration IP'),
                        TextEntry::make('country_code')
                            ->label('Country'),
                    ])->columns(2),

                InfolistSection::make('Limits & Quotas')
                    ->schema([
                        TextEntry::make('quota.agents_limit')
                            ->label('Agents Limit')
                            ->default(fn ($record) => $record?->quota?->agents_limit ?? 0),
                        TextEntry::make('quota.pages_limit')
                            ->label('Pages Limit')
                            ->default(fn ($record) => $record?->quota?->pages_limit ?? 0),
                        TextEntry::make('quota.links_limit')
                            ->label('Links Limit')
                            ->default(fn ($record) => $record?->quota?->links_limit ?? 0),
                        TextEntry::make('quota.minutes_limit')
                            ->label('Minutes Limit')
                            ->default(fn ($record) => $record?->quota?->minutes_limit ?? 0),
                        TextEntry::make('quota.custom_domains_limit')
                            ->label('Custom Domains Limit')
                            ->default(fn ($record) => $record?->quota?->custom_domains_limit ?? 0),
                        TextEntry::make('quota.members_limit')
                            ->label('Members Limit')
                            ->default(fn ($record) => $record?->quota?->members_limit ?? 0),
                    ])->columns(3),

                InfolistSection::make('Usage')
                    ->schema([
                        TextEntry::make('usage.messages')
                            ->label('Messages Used')
                            ->getStateUsing(function ($record): int {
                                return $record->usage?->messages ?? 0;
                            }),
                        TextEntry::make('tokens_used')
                            ->label('Tokens Used')
                            ->getStateUsing(function ($record): int {
                                return $record->messages()->sum('token_count') ?? 0;
                            })
                            ->formatStateUsing(fn ($state) => number_format($state)),
                        TextEntry::make('average_response_time')
                            ->label('Avg Response Time')
                            ->getStateUsing(function ($record): string {
                                $avgTime = $record->messages()
                                    ->whereNotNull('response_time_ms')
                                    ->avg('response_time_ms');

                                if (! $avgTime) {
                                    return 'N/A';
                                }

                                return number_format($avgTime, 0).' ms';
                            }),
                        TextEntry::make('most_used_model')
                            ->label('Most Used Model')
                            ->getStateUsing(function ($record): string {
                                $mostUsedModel = $record->messages()
                                    ->join('llms', 'llms.id', '=', 'chat_messages.llm_id')
                                    ->selectRaw('llms.internal_name as model, COUNT(*) as count')
                                    ->whereNotNull('chat_messages.llm_id')
                                    ->groupBy('llms.internal_name')
                                    ->orderByDesc('count')
                                    ->first();

                                return $mostUsedModel?->model ?? 'N/A';
                            }),
                    ])->columns(4),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('current_package')
                    ->label('Current Package')
                    ->getStateUsing(function (User $record): string {
                        $package = $record->getCurrentPackage();

                        return $package['name'] ?? 'Free';
                    })
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Free' => 'gray',
                        'Starter' => 'blue',
                        'Standard' => 'green',
                        'Pro' => 'purple',
                        default => 'gray',
                    }),
                TextColumn::make('subscription_status')
                    ->label('Subscription Status')
                    ->getStateUsing(function (User $record): string {
                        if ($record->onTrial()) {
                            return 'Trial';
                        }
                        if ($record->subscribed()) {
                            return 'Active';
                        }

                        return 'Inactive';
                    })
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Active' => 'success',
                        'Trial' => 'warning',
                        'Inactive' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('messages_count')
                    ->label('Messages')
                    ->getStateUsing(function (User $record): int {
                        return $record->messages()->count();
                    })
                    ->sortable(),
                TextColumn::make('agents_count')
                    ->label('Agents')
                    ->getStateUsing(function (User $record): int {
                        return $record->agents()->count();
                    })
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('last_login')
                    ->label('Last Login')
                    ->getStateUsing(function (User $record): string {
                        // You might want to track this in your User model
                        return $record->last_login_at?->diffForHumans();
                    })
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('subscription_status')
                    ->options([
                        'active' => 'Active',
                        'trial' => 'Trial',
                        'inactive' => 'Inactive',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['value']) {
                            'active' => $query->whereHas('subscriptions', fn ($q) => $q->where('stripe_status', 'active')),
                            'trial' => $query->whereNotNull('trial_ends_at')->where('trial_ends_at', '>', now()),
                            'inactive' => $query->whereDoesntHave('subscriptions')->where(function ($q) {
                                $q->whereNull('trial_ends_at')->orWhere('trial_ends_at', '<=', now());
                            }),
                            default => $query,
                        };
                    }),
                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('created_from'),
                        Forms\Components\DatePicker::make('created_until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'view' => Pages\ViewUser::route('/{record}'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}

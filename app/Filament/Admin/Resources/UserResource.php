<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Form;
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
                                    ->maxLength(255),
                            ]),
                    ]),

                Section::make('Subscription & Billing')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('stripe_id')
                                    ->label('Stripe Customer ID')
                                    ->maxLength(255)
                                    ->disabled(fn (string $context): bool => $context === 'edit')
                                    ->dehydrated(fn (string $context): bool => $context === 'create'),
                                Forms\Components\TextInput::make('pm_type')
                                    ->label('Payment Method Type')
                                    ->maxLength(255)
                                    ->disabled(fn (string $context): bool => $context === 'edit')
                                    ->dehydrated(fn (string $context): bool => $context === 'create'),
                            ]),
                        Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('pm_last_four')
                                    ->label('Last 4 Digits')
                                    ->maxLength(4)
                                    ->disabled(fn (string $context): bool => $context === 'edit')
                                    ->dehydrated(fn (string $context): bool => $context === 'create'),
                                Forms\Components\DateTimePicker::make('trial_ends_at')
                                    ->label('Trial Ends At')
                                    ->disabled(fn (string $context): bool => $context === 'edit')
                                    ->dehydrated(fn (string $context): bool => $context === 'create'),
                            ]),
                    ]),

                Section::make('Limits & Quotas')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                Forms\Components\TextInput::make('agents_instruction_limit')
                                    ->numeric()
                                    ->default(0),
                                Forms\Components\TextInput::make('agents_knowledge_limit')
                                    ->numeric()
                                    ->default(0),
                                Forms\Components\TextInput::make('pages_limit')
                                    ->numeric()
                                    ->default(0),
                            ]),
                        Grid::make(3)
                            ->schema([
                                Forms\Components\TextInput::make('minutes_limit')
                                    ->numeric()
                                    ->default(0),
                                Forms\Components\TextInput::make('custom_domains_limit')
                                    ->numeric()
                                    ->default(0),
                                Forms\Components\TextInput::make('members_limit')
                                    ->numeric()
                                    ->default(0),
                            ]),
                    ]),

                Section::make('Additional Information')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('google_id')
                                    ->maxLength(255)
                                    ->disabled(fn (string $context): bool => $context === 'edit')
                                    ->dehydrated(fn (string $context): bool => $context === 'create'),
                                Forms\Components\TextInput::make('microsoft_id')
                                    ->maxLength(255)
                                    ->disabled(fn (string $context): bool => $context === 'edit')
                                    ->dehydrated(fn (string $context): bool => $context === 'create'),
                            ]),
                        Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('registered_ip')
                                    ->disabled(fn (string $context): bool => $context === 'edit')
                                    ->dehydrated(fn (string $context): bool => $context === 'create'),
                                Forms\Components\TextInput::make('country_code')
                                    ->maxLength(5)
                                    ->disabled(fn (string $context): bool => $context === 'edit')
                                    ->dehydrated(fn (string $context): bool => $context === 'create'),
                            ]),
                    ]),
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

<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\MessageResource\Pages;
use App\Models\Message;
use Filament\Forms;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MessageResource extends Resource
{
    protected static ?string $model = Message::class;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?string $navigationGroup = 'Analytics';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Message Information')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('conversation_id')
                                    ->required()
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('generation_id')
                                    ->maxLength(255),
                            ]),
                        Grid::make(2)
                            ->schema([
                                Forms\Components\Select::make('role')
                                    ->options([
                                        'user' => 'User',
                                        'assistant' => 'Assistant',
                                    ])
                                    ->required(),
                                Forms\Components\TextInput::make('model')
                                    ->maxLength(255),
                            ]),
                        Forms\Components\Textarea::make('content')
                            ->required()
                            ->maxLength(65535)
                            ->columnSpanFull(),
                        Grid::make(3)
                            ->schema([
                                Forms\Components\TextInput::make('token_count')
                                    ->numeric(),
                                Forms\Components\TextInput::make('response_time_ms')
                                    ->numeric(),
                                Forms\Components\Select::make('feedback')
                                    ->options([
                                        'positive' => 'Positive',
                                        'negative' => 'Negative',
                                    ]),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->label('User')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('agent.name')
                    ->label('Agent')
                    ->url(fn (Message $record) => $record->agent?->url)
                    ->openUrlInNewTab()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('role')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'user' => 'blue',
                        'assistant' => 'green',
                        default => 'gray',
                    }),
                TextColumn::make('model')
                    ->label('Model')
                    ->searchable(),
                TextColumn::make('token_count')
                    ->label('Tokens')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('cost')
                    ->label('Cost')
                    ->getStateUsing(function (Message $record): string {
                        $models = config('llms', []);
                        $modelKey = strtolower((string) ($record->model ?? ''));
                        $entry = collect($models)->first(function ($m) use ($modelKey) {
                            return strtolower($m['internal_name'] ?? '') === $modelKey
                                || strtolower($m['external_name'] ?? '') === $modelKey
                                || strtolower($m['descriptive_name'] ?? '') === $modelKey;
                        });

                        $ppm = $entry['price_per_million_tokens'] ?? null;
                        $tokens = (int) ($record->token_count ?? 0);
                        if (! $ppm || $tokens <= 0) {
                            return '—';
                        }

                        $rate = $record->role === 'assistant'
                            ? ($ppm['output'] ?? null)
                            : ($ppm['input'] ?? null);

                        if ($rate === null) {
                            return '—';
                        }

                        $cost = ($tokens / 1000000) * (float) $rate;

                        return '$'.number_format($cost, 6);
                    }),
                TextColumn::make('response_time_ms')
                    ->label('Response Time (ms)')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('feedback')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'positive' => 'success',
                        'negative' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('role')
                    ->options([
                        'user' => 'User',
                        'assistant' => 'Assistant',
                    ]),
                Tables\Filters\SelectFilter::make('feedback')
                    ->options([
                        'positive' => 'Positive',
                        'negative' => 'Negative',
                    ]),
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
            'index' => Pages\ListMessages::route('/'),
            'view' => Pages\ViewMessage::route('/{record}'),
        ];
    }
}

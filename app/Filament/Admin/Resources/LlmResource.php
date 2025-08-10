<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\LlmResource\Pages;
use App\Models\Llm;
use Filament\Forms;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class LlmResource extends Resource
{
    protected static ?string $model = Llm::class;

    protected static ?string $navigationIcon = 'heroicon-o-cpu-chip';

    protected static ?string $navigationGroup = 'AI Management';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'LLM Model';

    protected static ?string $pluralModelLabel = 'LLM Models';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Basic Information')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('internal_name')
                                    ->required()
                                    ->maxLength(255)
                                    ->helperText('Internal identifier used in the system')
                                    ->placeholder('gpt-5'),
                                Forms\Components\TextInput::make('external_name')
                                    ->required()
                                    ->maxLength(255)
                                    ->helperText('Name displayed to users')
                                    ->placeholder('openai/gpt-5'),
                            ]),
                        Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('descriptive_name')
                                    ->required()
                                    ->maxLength(255)
                                    ->helperText('Full descriptive name')
                                    ->placeholder('GPT-4 Turbo'),
                                Forms\Components\TextInput::make('icon')
                                    ->required()
                                    ->maxLength(255)
                                    ->helperText('Icon identifier (e.g., gpt.svg, claude.svg)')
                                    ->placeholder('gpt.svg'),
                            ]),
                        Forms\Components\Textarea::make('description')
                            ->required()
                            ->rows(3)
                            ->helperText('Detailed description of the model capabilities')
                            ->placeholder('Advanced language model with enhanced reasoning capabilities...'),
                    ]),

                Section::make('Technical Specifications')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                Forms\Components\TextInput::make('context_window')
                                    ->required()
                                    ->numeric()
                                    ->minValue(1)
                                    ->helperText('Maximum context window in tokens')
                                    ->placeholder('128000'),
                                Forms\Components\Select::make('location')
                                    ->required()
                                    ->options([
                                        'us' => 'United States',
                                        'eu' => 'Europe',
                                        'cn' => 'China',
                                    ])
                                    ->helperText('Geographic location of the model'),
                                Forms\Components\Toggle::make('reasoning')
                                    ->label('Advanced Reasoning')
                                    ->helperText('Whether this model supports advanced reasoning'),
                            ]),
                        Grid::make(2)
                            ->schema([
                                Forms\Components\Toggle::make('requires_paid_plan')
                                    ->label('Requires Paid Plan')
                                    ->helperText('Whether this model is only available to paid users'),
                                Forms\Components\KeyValue::make('features')
                                    ->label('Features')
                                    ->keyLabel('Feature')
                                    ->valueLabel('Value')
                                    ->helperText('Additional features and capabilities as key-value pairs')
                                    ->addActionLabel('Add Feature'),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('internal_name')
                    ->label('Internal Name')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->fontFamily('mono'),
                TextColumn::make('external_name')
                    ->label('Display Name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('descriptive_name')
                    ->label('Full Name')
                    ->searchable()
                    ->sortable()
                    ->limit(30),
                TextColumn::make('context_window')
                    ->label('Context Window')
                    ->numeric()
                    ->sortable()
                    ->formatStateUsing(fn (int $state): string => number_format($state).' tokens'),
                TextColumn::make('location')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'us' => 'success',
                        'eu' => 'info',
                        'cn' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => strtoupper($state)),
                IconColumn::make('reasoning')
                    ->label('Reasoning')
                    ->boolean()
                    ->trueIcon('heroicon-o-light-bulb')
                    ->falseIcon('heroicon-o-x-mark')
                    ->trueColor('success')
                    ->falseColor('gray'),
                IconColumn::make('requires_paid_plan')
                    ->label('Paid Only')
                    ->boolean()
                    ->trueIcon('heroicon-o-currency-dollar')
                    ->falseIcon('heroicon-o-gift')
                    ->trueColor('warning')
                    ->falseColor('success'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('location')
                    ->options([
                        'us' => 'United States',
                        'eu' => 'Europe',
                        'cn' => 'China',
                    ]),
                Tables\Filters\TernaryFilter::make('reasoning')
                    ->label('Advanced Reasoning'),
                Tables\Filters\TernaryFilter::make('requires_paid_plan')
                    ->label('Paid Plan Required'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
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
            'index' => Pages\ListLlms::route('/'),
            'create' => Pages\CreateLlm::route('/create'),
            'view' => Pages\ViewLlm::route('/{record}'),
            'edit' => Pages\EditLlm::route('/{record}/edit'),
        ];
    }
}

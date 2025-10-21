<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\ChangelogResource\Pages;
use App\Models\Changelog;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ChangelogResource extends Resource
{
    protected static ?string $model = Changelog::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'Content Management';

    protected static ?string $pluralLabel = 'Changelogs';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Version Information')
                    ->schema([
                        Forms\Components\TextInput::make('version')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->placeholder('e.g., 21-10-2025'),
                        Forms\Components\DatePicker::make('release_date')
                            ->required()
                            ->native(false)
                            ->displayFormat('M d, Y')
                            ->default(now()),
                    ]),
                Forms\Components\Section::make('Changes')
                    ->schema([
                        Forms\Components\Repeater::make('items')
                            ->relationship('items')
                            ->schema([
                                Forms\Components\TextInput::make('description')
                                    ->required()
                                    ->placeholder('Enter change description...')
                                    ->columnSpan(2),
                                Forms\Components\Select::make('type')
                                    ->required()
                                    ->options([
                                        'new_features' => 'New Features',
                                        'improvements' => 'Improvements',
                                        'bug_fixes' => 'Bug Fixes',
                                        'breaking_changes' => 'Breaking Changes',
                                        'core_features' => 'Core Features',
                                    ])
                                    ->default('new_features')
                                    ->columnSpan(1),
                            ])
                            ->columns(3)
                            ->reorderable(false)
                            ->defaultItems(1)
                            ->addActionLabel('Add Change')
                            ->deleteAction(
                                fn ($action) => $action->requiresConfirmation(false)
                            ),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('version')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('release_date')
                    ->dateTime('M d, Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('items_count')
                    ->counts('items')
                    ->label('Changes')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('release_date', 'desc')
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
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
            'index' => Pages\ListChangelogs::route('/'),
            'create' => Pages\CreateChangelog::route('/create'),
            'view' => Pages\ViewChangelog::route('/{record}'),
            'edit' => Pages\EditChangelog::route('/{record}/edit'),
        ];
    }
}

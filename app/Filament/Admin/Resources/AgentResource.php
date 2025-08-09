<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\AgentResource\Pages;
use App\Models\Agent;
use Filament\Forms;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\Section as InfolistSection;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AgentResource extends Resource
{
    protected static ?string $model = Agent::class;

    protected static ?string $navigationIcon = 'heroicon-o-cpu-chip';

    protected static ?string $navigationGroup = 'Analytics';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Agent Information')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->required()
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('sub_domain')
                                    ->label('Subdomain')
                                    ->required()
                                    ->maxLength(255),
                            ]),
                        Forms\Components\TextInput::make('domain')
                            ->label('Custom Domain')
                            ->maxLength(255),
                        Textarea::make('system_instructions')
                            ->label('System Instructions')
                            ->rows(6)
                            ->columnSpanFull(),
                        Select::make('type')
                            ->options([
                                'knowledge' => 'Knowledge',
                                'instruction' => 'Instruction',
                            ])
                            ->required(),
                        Grid::make(2)
                            ->schema([
                                Toggle::make('is_active')
                                    ->required(),
                                Toggle::make('is_public')
                                    ->required(),
                            ]),
                    ]),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                InfolistSection::make('Agent')
                    ->schema([
                        TextEntry::make('name'),
                        TextEntry::make('sub_domain')->label('Subdomain'),
                        TextEntry::make('domain')->label('Custom Domain')->default('-'),
                        TextEntry::make('type')->badge(),
                        IconEntry::make('is_active')->boolean()->label('Active'),
                        IconEntry::make('is_public')->boolean()->label('Public'),
                        TextEntry::make('system_instructions')->label('System Instructions')->columnSpanFull(),
                    ])->columns(2),
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
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('sub_domain')
                    ->label('Subdomain')
                    ->searchable(),
                TextColumn::make('domain')
                    ->label('Domain')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('is_active')
                    ->label('Active')
                    ->badge()
                    ->color(fn (bool $state): string => $state ? 'success' : 'danger'),
                TextColumn::make('is_public')
                    ->label('Public')
                    ->badge()
                    ->color(fn (bool $state): string => $state ? 'success' : 'gray'),
                TextColumn::make('messages_count')
                    ->label('Messages')
                    ->getStateUsing(function (Agent $record): int {
                        return $record->messages()->count();
                    })
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active Status'),
                Tables\Filters\TernaryFilter::make('is_public')
                    ->label('Public Status'),
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
            'index' => Pages\ListAgents::route('/'),
            'view' => Pages\ViewAgent::route('/{record}'),
            'edit' => Pages\EditAgent::route('/{record}/edit'),
        ];
    }
}

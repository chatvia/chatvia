<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\BlogResource\Pages;
use App\Models\Post;
use Filament\Forms;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class BlogResource extends Resource
{
    protected static ?string $model = Post::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'Content Management';

    protected static ?string $navigationLabel = 'Blog Posts';

    protected static ?string $pluralLabel = 'Blog Posts';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Post Content')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('title')
                                    ->required()
                                    ->maxLength(255)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn ($state, Forms\Set $set) => $set('slug', Str::slug($state))),
                                Forms\Components\TextInput::make('slug')
                                    ->required()
                                    ->maxLength(255)
                                    ->unique(ignoreRecord: true)
                                    ->rules(['alpha_dash']),
                            ]),
                        Forms\Components\Textarea::make('excerpt')
                            ->rows(3)
                            ->maxLength(500)
                            ->helperText('Brief description of the post (optional)'),
                        Forms\Components\MarkdownEditor::make('markdown')
                            ->required()
                            ->columnSpanFull()
                            ->helperText('Write your post content in Markdown. HTML will be generated automatically.'),
                    ]),

                Section::make('Post Settings')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Forms\Components\Select::make('status')
                                    ->required()
                                    ->options([
                                        'draft' => 'Draft',
                                        'published' => 'Published',
                                    ])
                                    ->default('draft')
                                    ->live(),
                                Forms\Components\DateTimePicker::make('published_at')
                                    ->label('Publish Date')
                                    ->visible(fn (Forms\Get $get): bool => $get('status') === 'published')
                                    ->default(now())
                                    ->required(fn (Forms\Get $get): bool => $get('status') === 'published'),
                            ]),
                        Grid::make(2)
                            ->schema([
                                Forms\Components\Toggle::make('featured')
                                    ->label('Featured Post')
                                    ->helperText('Show this post as featured'),
                                Forms\Components\Toggle::make('popular')
                                    ->label('Popular Post')
                                    ->helperText('Mark this post as popular'),
                            ]),
                    ]),

                Section::make('Featured Image')
                    ->schema([
                        Forms\Components\FileUpload::make('image_path')
                            ->label('Featured Image')
                            ->image()
                            ->disk('scaleway')
                            ->directory('blog/images')
                            ->visibility('public')
                            ->imageEditor()
                            ->imageEditorAspectRatios([
                                '16:9',
                                '4:3',
                                '1:1',
                            ])
                            ->maxSize(5120) // 5MB
                            ->helperText('Upload a featured image for your post (max 5MB).'),
                    ]),

                Section::make('Meta Data')
                    ->schema([
                        Forms\Components\KeyValue::make('meta')
                            ->label('Meta Fields')
                            ->addActionLabel('Add Meta Field')
                            ->helperText('Additional metadata for SEO or custom fields')
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->limit(50),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'gray',
                        'published' => 'success',
                        default => 'gray',
                    }),
                Tables\Columns\IconColumn::make('featured')
                    ->boolean()
                    ->label('Featured'),
                Tables\Columns\IconColumn::make('popular')
                    ->boolean()
                    ->label('Popular'),
                Tables\Columns\ImageColumn::make('image_path')
                    ->label('Image')
                    ->disk('s3')
                    ->size(50),
                TextColumn::make('published_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('views')
                    ->numeric()
                    ->sortable()
                    ->toggleable(),
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
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'published' => 'Published',
                    ]),
                Tables\Filters\TernaryFilter::make('featured')
                    ->label('Featured Posts'),
                Tables\Filters\TernaryFilter::make('popular')
                    ->label('Popular Posts'),
                Tables\Filters\Filter::make('published_at')
                    ->form([
                        Forms\Components\DatePicker::make('published_from')
                            ->label('Published From'),
                        Forms\Components\DatePicker::make('published_until')
                            ->label('Published Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['published_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('published_at', '>=', $date),
                            )
                            ->when(
                                $data['published_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('published_at', '<=', $date),
                            );
                    }),
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
            'index' => Pages\ListBlogs::route('/'),
            'create' => Pages\CreateBlog::route('/create'),
            'view' => Pages\ViewBlog::route('/{record}'),
            'edit' => Pages\EditBlog::route('/{record}/edit'),
        ];
    }
}

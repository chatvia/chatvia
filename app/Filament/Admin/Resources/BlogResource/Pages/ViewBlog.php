<?php

namespace App\Filament\Admin\Resources\BlogResource\Pages;

use App\Filament\Admin\Resources\BlogResource;
use Filament\Actions;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewBlog extends ViewRecord
{
    protected static string $resource = BlogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Post Details')
                    ->schema([
                        TextEntry::make('title')
                            ->size(TextEntry\TextEntrySize::Large),
                        TextEntry::make('slug')
                            ->label('URL Slug'),
                        TextEntry::make('excerpt')
                            ->prose()
                            ->markdown()
                            ->placeholder('No excerpt provided'),
                        TextEntry::make('status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'draft' => 'gray',
                                'published' => 'success',
                                default => 'gray',
                            }),
                        TextEntry::make('published_at')
                            ->dateTime()
                            ->placeholder('Not published yet'),
                    ])->columns(2),

                Section::make('Content')
                    ->schema([
                        TextEntry::make('html')
                            ->label('Rendered Content')
                            ->html()
                            ->prose()
                            ->columnSpanFull(),
                    ]),

                Section::make('Settings')
                    ->schema([
                        TextEntry::make('featured')
                            ->badge()
                            ->getStateUsing(fn ($record) => $record->featured ? 'Featured' : 'Not Featured')
                            ->color(fn ($record) => $record->featured ? 'success' : 'gray'),
                        TextEntry::make('popular')
                            ->badge()
                            ->getStateUsing(fn ($record) => $record->popular ? 'Popular' : 'Not Popular')
                            ->color(fn ($record) => $record->popular ? 'success' : 'gray'),
                        TextEntry::make('views')
                            ->numeric()
                            ->label('View Count'),
                    ])->columns(3),

                Section::make('Featured Image')
                    ->schema([
                        ImageEntry::make('image_path')
                            ->label('')
                            ->disk('scaleway')
                            ->size(400),
                    ])
                    ->visible(fn ($record) => $record->image_path),

                Section::make('Meta Data')
                    ->schema([
                        TextEntry::make('meta')
                            ->label('Additional Meta Fields')
                            ->listWithLineBreaks()
                            ->placeholder('No meta data'),
                        TextEntry::make('created_at')
                            ->dateTime()
                            ->label('Created'),
                        TextEntry::make('updated_at')
                            ->dateTime()
                            ->label('Last Updated'),
                    ])->columns(2),
            ]);
    }
}

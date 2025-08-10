<?php

namespace App\Filament\Admin\Resources\LlmResource\Pages;

use App\Filament\Admin\Resources\LlmResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListLlms extends ListRecords
{
    protected static string $resource = LlmResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Add LLM Model'),
        ];
    }
}

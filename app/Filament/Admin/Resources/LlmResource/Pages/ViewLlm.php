<?php

namespace App\Filament\Admin\Resources\LlmResource\Pages;

use App\Filament\Admin\Resources\LlmResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewLlm extends ViewRecord
{
    protected static string $resource = LlmResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}

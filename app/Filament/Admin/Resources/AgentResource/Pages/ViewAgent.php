<?php

namespace App\Filament\Admin\Resources\AgentResource\Pages;

use App\Filament\Admin\Resources\AgentResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewAgent extends ViewRecord
{
    protected static string $resource = AgentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}

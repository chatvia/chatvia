<?php

namespace App\Filament\Admin\Resources\ChangelogResource\Pages;

use App\Filament\Admin\Resources\ChangelogResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewChangelog extends ViewRecord
{
    protected static string $resource = ChangelogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}

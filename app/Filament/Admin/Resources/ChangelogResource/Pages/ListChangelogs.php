<?php

namespace App\Filament\Admin\Resources\ChangelogResource\Pages;

use App\Filament\Admin\Resources\ChangelogResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListChangelogs extends ListRecords
{
    protected static string $resource = ChangelogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

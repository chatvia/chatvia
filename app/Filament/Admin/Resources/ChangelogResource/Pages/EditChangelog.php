<?php

namespace App\Filament\Admin\Resources\ChangelogResource\Pages;

use App\Filament\Admin\Resources\ChangelogResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditChangelog extends EditRecord
{
    protected static string $resource = ChangelogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
            Actions\ForceDeleteAction::make(),
            Actions\RestoreAction::make(),
        ];
    }
}

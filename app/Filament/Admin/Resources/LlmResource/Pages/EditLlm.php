<?php

namespace App\Filament\Admin\Resources\LlmResource\Pages;

use App\Filament\Admin\Resources\LlmResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditLlm extends EditRecord
{
    protected static string $resource = LlmResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}

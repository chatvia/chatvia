<?php

namespace App\Filament\Admin\Resources\LlmResource\Pages;

use App\Filament\Admin\Resources\LlmResource;
use Filament\Resources\Pages\CreateRecord;

class CreateLlm extends CreateRecord
{
    protected static string $resource = LlmResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}

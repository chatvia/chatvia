<?php

namespace App\Filament\Admin\Resources\BlogResource\Pages;

use App\Filament\Admin\Resources\BlogResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Mail\Markdown;

class EditBlog extends EditRecord
{
    protected static string $resource = BlogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        // Generate HTML from Markdown
        if (isset($data['markdown'])) {
            $data['html'] = $this->convertMarkdownToHtml($data['markdown']);
        }

        // Set published_at if status changed to published and not already set
        if ($data['status'] === 'published' && !$record->published_at && empty($data['published_at'])) {
            $data['published_at'] = now();
        }

        $record->update($data);

        return $record;
    }

    private function convertMarkdownToHtml(string $markdown): string
    {
        return Markdown::parse($markdown)->toHtml();
    }
}

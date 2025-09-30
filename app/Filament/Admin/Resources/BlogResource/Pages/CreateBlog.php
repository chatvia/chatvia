<?php

namespace App\Filament\Admin\Resources\BlogResource\Pages;

use App\Filament\Admin\Resources\BlogResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Mail\Markdown;

class CreateBlog extends CreateRecord
{
    protected static string $resource = BlogResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        // Generate HTML from Markdown
        if (isset($data['markdown'])) {
            $data['html'] = $this->convertMarkdownToHtml($data['markdown']);
        }

        // Set published_at if status is published and not already set
        if ($data['status'] === 'published' && empty($data['published_at'])) {
            $data['published_at'] = now();
        }

        // Create the record first
        $record = $this->getModel()::create($data);

        return $record;
    }

    private function convertMarkdownToHtml(string $markdown): string
    {
        return Markdown::parse($markdown)->toHtml();
    }
}

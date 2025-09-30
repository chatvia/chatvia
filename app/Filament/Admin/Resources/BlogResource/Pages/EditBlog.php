<?php

namespace App\Filament\Admin\Resources\BlogResource\Pages;

use App\Filament\Admin\Resources\BlogResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

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
        // Using a simple markdown to HTML conversion
        // You can enhance this with a proper Markdown parser like Parsedown or CommonMark
        $html = $markdown;
        
        // Convert headers
        $html = preg_replace('/^### (.*$)/im', '<h3>$1</h3>', $html);
        $html = preg_replace('/^## (.*$)/im', '<h2>$1</h2>', $html);
        $html = preg_replace('/^# (.*$)/im', '<h1>$1</h1>', $html);
        
        // Convert bold and italic
        $html = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $html);
        $html = preg_replace('/\*(.*?)\*/', '<em>$1</em>', $html);
        
        // Convert links
        $html = preg_replace('/\[([^\]]+)\]\(([^)]+)\)/', '<a href="$2">$1</a>', $html);
        
        // Convert line breaks to paragraphs
        $html = '<p>' . preg_replace('/\n\s*\n/', '</p><p>', $html) . '</p>';
        $html = str_replace('<p></p>', '', $html);
        
        return $html;
    }
}

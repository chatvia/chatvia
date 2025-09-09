<?php

namespace App\Http\Controllers\Chat\Export;

use App\Services\Memory\ChatMemory;
use App\Services\Memory\ChatSession;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Spatie\Browsershot\Browsershot;
use Spatie\LaravelMarkdown\MarkdownRenderer;
use Spatie\LaravelPdf\Facades\Pdf;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

readonly class ChatExportController
{
    public function __construct(private ChatMemory $memory, private ChatSession $session) {}

    public function __invoke(Request $request, string $conversationId)
    {
        $request->validate([
            'messageId' => ['sometimes'],
            'type' => ['required', 'string', 'in:pdf,text,markdown'],
        ]);

        $this
            ->memory
            ->setSessionId($this->session->firstOrCreateChatSession())
            ->setAgent(current_agent())
            ->setConversationId($conversationId);

        $type = $request->input('type');

        $messageId = $request->input('messageId');

        $messages = [];

        if ($messageId) {
            $messages[] = $this->memory->getMessageById($messageId);
        } else {
            $messages = $this->memory->getMessagesForConversation($conversationId);
        }

        return match ($type) {
            'pdf' => $this->toPdf($messages, $this->filename($conversationId)),
            'markdown' => $this->toMarkdown($messages, $this->filename($conversationId)),
            'text' => $this->toText($messages, $this->filename($conversationId)),
            'default' => throw new Exception('Unsupported export type'),
        };
    }

    private function filename(string $conversationId): string
    {
        $conversation = $this->memory->getConversationById($conversationId);

        return Str::slug($conversation['title']);
    }

    private function toPdf(array $messages, string $filename)
    {
        return Pdf::withBrowsershot(function (Browsershot $browsershot) {
            $browsershot
                ->setIncludePath(config('services.bin.nvm_include_path'))
                ->setNodeBinary(config('services.bin.node'))
                ->setNpmBinary(config('services.bin.npm'));
        })->view('chat.export.pdf', [
            'messages' => $messages,
        ])->download($filename.'.pdf');
    }

    private function toMarkdown(array $messages, string $filename): BinaryFileResponse
    {
        $content = collect($messages)->map(function ($message) {
            return $message['content'];
        })->implode("---\n");

        $file = create_temp_file($filename, $content);

        return response()->download($file, $filename.'.md');
    }

    private function toText(array $messages, string $filename): BinaryFileResponse
    {
        $content = collect($messages)->map(function ($message) {
            return strip_tags(app(MarkdownRenderer::class)->toHtml($message['content']));
        })->implode("---\n");

        $file = create_temp_file($filename, $content);

        return response()->download($file, $filename.'.txt');
    }
}

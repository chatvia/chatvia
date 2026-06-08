<?php

namespace App\Http\Controllers\Chat\Export;

use Domain\Conversation\Services\Memory\ChatMemory;
use Domain\Conversation\Services\Memory\TurnState;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Spatie\Browsershot\Browsershot;
use Spatie\LaravelMarkdown\MarkdownRenderer;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;
use Support\Browsershot\BrowsershotConfigurator;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

readonly class ChatExportController
{
    public function __construct(
        private ChatMemory $memory,
        private TurnState $session,
        private BrowsershotConfigurator $browsershotConfig,
    ) {}

    public function __invoke(Request $request, string $conversationId)
    {
        $request->validate([
            'messageId' => ['sometimes'],
            'type' => ['required', 'string', 'in:pdf,text,markdown'],
        ]);

        $agent = current_agent();

        $this
            ->memory
            ->withContext($agent, $this->session->firstOrCreateSession($agent), $conversationId);

        $type = $request->input('type');

        $messageId = $request->input('messageId');

        $messages = [];

        if ($messageId) {
            $messages[] = $this->memory->getMessageById($messageId);
        } else {
            $messages = $this->memory->getMessagesForConversation();
        }

        return match ($type) {
            'pdf' => $this->toPdf($messages, $this->filename($conversationId)),
            'markdown' => $this->toMarkdown($messages, $this->filename($conversationId)),
            'text' => $this->toText($messages, $this->filename($conversationId)),
            default => throw new Exception('Unsupported export type'),
        };
    }

    private function filename(string $conversationId): string
    {
        $conversation = $this->memory->getConversationById($conversationId);

        return Str::slug($conversation->title);
    }

    private function toPdf(array $messages, string $filename): PdfBuilder
    {
        return Pdf::withBrowsershot(fn (Browsershot $browsershot): Browsershot => $this->browsershotConfig->configure($browsershot))
            ->view('chat.export.pdf', ['messages' => $messages])
            ->download($filename.'.pdf');
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

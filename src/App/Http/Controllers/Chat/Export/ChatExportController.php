<?php

namespace App\Http\Controllers\Chat\Export;

use App\Http\Chat\HostedChatSession;
use App\Http\Requests\Chat\ExportChatRequest;
use Domain\Conversation\Enums\ChatExportFormat;
use Domain\Conversation\Services\Memory\ChatMemory;
use Illuminate\Support\Str;
use Spatie\Browsershot\Browsershot;
use Spatie\LaravelMarkdown\MarkdownRenderer;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;
use Support\Browsershot\BrowsershotConfigurator;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Downloads one of the hosted-chat visitor's conversations — or a single
 * message of it — as a PDF, plain text or Markdown file.
 */
readonly class ChatExportController
{
    /** Separator between messages in the text and Markdown files. */
    private const string MESSAGE_SEPARATOR = "---\n";

    public function __construct(
        private ChatMemory $memory,
        private HostedChatSession $session,
        private BrowsershotConfigurator $browsershotConfig,
        private MarkdownRenderer $markdown,
    ) {}

    public function __invoke(ExportChatRequest $request, string $conversationId): PdfBuilder|BinaryFileResponse
    {
        $this->memory->withScope($this->session->scope(current_agent(), $conversationId));

        $messages = $this->messages($request->messageId());
        $filename = Str::slug($this->memory->getConversationById($conversationId)?->title ?? '');

        return match ($request->exportFormat()) {
            ChatExportFormat::Pdf => $this->toPdf($messages, $filename),
            ChatExportFormat::Markdown => $this->download($messages, static fn (array $message): string => $message['content'], $filename.'.md'),
            ChatExportFormat::Text => $this->download($messages, fn (array $message): string => strip_tags($this->markdown->toHtml($message['content'])), $filename.'.txt'),
        };
    }

    /**
     * The whole history, or just the asked-for message.
     *
     * @return array<int, array<string, mixed>>
     */
    private function messages(?string $messageId): array
    {
        if ($messageId === null) {
            return $this->memory->getMessagesForConversation();
        }

        $message = $this->memory->getMessageById($messageId);

        abort_if($message === null, 404);

        return [$message->toArray()];
    }

    /**
     * @param  array<int, array<string, mixed>>  $messages
     */
    private function toPdf(array $messages, string $filename): PdfBuilder
    {
        return Pdf::withBrowsershot(fn (Browsershot $browsershot): Browsershot => $this->browsershotConfig->configure($browsershot))
            ->view('chat.export.pdf', ['messages' => $messages])
            ->download($filename.'.pdf');
    }

    /**
     * @param  array<int, array<string, mixed>>  $messages
     * @param  callable(array<string, mixed>): string  $render
     */
    private function download(array $messages, callable $render, string $filename): BinaryFileResponse
    {
        $content = collect($messages)->map($render)->implode(self::MESSAGE_SEPARATOR);

        return response()->download(create_temp_file($filename, $content), $filename);
    }
}

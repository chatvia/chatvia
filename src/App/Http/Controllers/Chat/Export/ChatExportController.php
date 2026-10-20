<?php

namespace App\Http\Controllers\Chat\Export;

use App\Http\Chat\HostedChatAgent;
use App\Http\Chat\HostedChatSession;
use App\Http\Requests\Chat\ExportChatRequest;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Data\Memory\MessageData;
use Domain\Conversation\Enums\ChatExportFormat;
use Domain\Conversation\Services\Export\ChatTranscriptRenderer;
use Domain\Conversation\Services\Memory\ChatMemory;
use Illuminate\Support\Str;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Downloads one of the hosted-chat visitor's conversations — or a single
 * message of it — as a PDF, plain text or Markdown file
 * ({@see ChatTranscriptRenderer}).
 */
readonly class ChatExportController
{
    public function __construct(
        private ChatMemory $memory,
        private HostedChatSession $session,
        private ChatTranscriptRenderer $renderer,
    ) {}

    public function __invoke(
        ExportChatRequest $request,
        string $conversationId,
        #[HostedChatAgent] Agent $agent,
    ): PdfBuilder|BinaryFileResponse {
        $this->memory->withScope($this->session->scope($agent, $conversationId));

        $messages = $this->messages($request->messageId());
        $filename = Str::slug($this->memory->getConversationById($conversationId)?->title ?? '');

        return match ($request->exportFormat()) {
            // Made by the PDF maker of Settings → Platform, which may load only
            // data: URLs (WeasyPrint's allowed-protocols, the view's
            // Content-Security-Policy for a browser), so a link in a message
            // cannot make the server fetch it.
            ChatExportFormat::Pdf => Pdf::view('chat.export.pdf', ['messages' => $this->renderer->toPdfMessages($messages)])
                ->download($filename.'.pdf'),
            ChatExportFormat::Markdown => $this->download($this->renderer->toMarkdown($messages), $filename.'.md'),
            ChatExportFormat::Text => $this->download($this->renderer->toText($messages), $filename.'.txt'),
        };
    }

    /**
     * The whole history, or just the asked-for message.
     *
     * @return list<MessageData>
     */
    private function messages(?string $messageId): array
    {
        if ($messageId === null) {
            return $this->memory->getMessagesForConversation();
        }

        $message = $this->memory->getMessageById($messageId);

        abort_if(! $message instanceof MessageData, 404);

        return [$message];
    }

    private function download(string $content, string $filename): BinaryFileResponse
    {
        return response()->download(create_temp_file($filename, $content), $filename);
    }
}

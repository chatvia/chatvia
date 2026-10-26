<?php

declare(strict_types=1);

namespace Domain\Conversation\Services\Media;

use Domain\Conversation\Data\Media\AttachedTextData;
use Domain\Conversation\Models\Attachment;
use Domain\Conversation\Models\Conversation;
use Domain\Knowledge\Ingestion\Services\Parsing\LocalDocumentParser;
use Domain\Llm\Enums\FileInput;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\File\File;
use Throwable;

/**
 * Reads the text of the latest PDF a chatter attached to a conversation, so
 * an agent can have it read aloud without copying it word by word. It reads
 * the text layer on the server (`pdftotext`, {@see LocalDocumentParser}),
 * so a scan or an image has no text here.
 */
final readonly class AttachedFileText
{
    public function __construct(
        private LocalDocumentParser $parser,
    ) {}

    /** The latest attached PDF and its text; null when the chat has no PDF. */
    public function latestPdf(Conversation $conversation): ?AttachedTextData
    {
        $attachment = $conversation->attachments()
            ->latest('id')
            ->get()
            ->first(static fn (Attachment $attachment): bool => FileInput::forMimeType($attachment->mime_type) === FileInput::Pdf);

        if (! $attachment instanceof Attachment) {
            return null;
        }

        return new AttachedTextData($attachment->filename, $this->text($attachment));
    }

    private function text(Attachment $attachment): string
    {
        try {
            return $attachment->withLocalFile(fn (File $file): string => $this->parser
                ->parse($file->getPathname(), $file->getPath())
                ->pluck('content')
                ->filter()
                ->implode("\n\n"));
        } catch (Throwable $e) {
            Log::warning('An attached PDF could not be read for an audio file', [
                'attachment_id' => $attachment->id,
                'exception' => $e::class,
                'message' => Str::limit($e->getMessage(), 300),
            ]);

            return '';
        }
    }
}

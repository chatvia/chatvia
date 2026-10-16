<?php

namespace Domain\Conversation\Actions\Attachments;

use Domain\Conversation\Actions\Conversations\CreateConversationAction;
use Domain\Conversation\Data\Attachments\UploadAttachmentData;
use Domain\Conversation\Models\Attachment;
use Domain\Knowledge\Document\Contracts\PdfInspector;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Persist a visitor- or member-uploaded attachment to the public Scaleway
 * bucket and create the matching {@see Attachment} row. The DB row
 * persists immediately whether or not the conversation already exists —
 * the Home.vue path uploads first and {@see CreateConversationAction}
 * claims orphan rows by external_id when the conversation is created.
 */
class StoreAttachmentAction
{
    public function __construct(private readonly PdfInspector $pdfInspector) {}

    public function execute(UploadAttachmentData $data): Attachment
    {
        // Generate the storage filename from a fresh UUID. The model's
        // external_id is auto-filled by HasExternalId on `creating`,
        // and it's intentionally not on Fillable — we want callers using
        // ::create() to never overwrite it.
        $extension = strtolower($data->file->getClientOriginalExtension() ?: 'pdf');
        $directory = trim($data->directory, '/');
        $filename = Str::uuid().'.'.$extension;
        $filepath = $directory.'/'.$filename;

        Storage::disk(Attachment::DISK)
            ->putFileAs($directory, $data->file, $filename);

        return Attachment::create([
            'workspace_id' => $data->workspaceId,
            'chat_conversation_id' => $data->conversationId,
            'filepath' => $filepath,
            'filename' => $data->file->getClientOriginalName(),
            'mime_type' => $data->file->getMimeType() ?? 'application/octet-stream',
            'size_bytes' => $data->file->getSize() ?: 0,
            'pages' => $this->pageCount($data),
        ]);
    }

    /**
     * Page count for PDFs, captured here so it can be shown with the file
     * without re-downloading it from S3. Non-PDFs and files pdfinfo can't
     * read return null — validation already gates real PDFs, so this only ever
     * fails soft on an edge case rather than 500-ing the upload.
     */
    private function pageCount(UploadAttachmentData $data): ?int
    {
        if (! is_pdf_file($data->file)) {
            return null;
        }

        try {
            return $this->pdfInspector->getInfo($data->file->getPathname())->length;
        } catch (Throwable) {
            return null;
        }
    }
}

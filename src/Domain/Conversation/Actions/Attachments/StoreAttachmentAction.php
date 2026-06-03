<?php

namespace Domain\Conversation\Actions\Attachments;

use Domain\Conversation\Actions\Conversations\CreateConversationAction;
use Domain\Conversation\Data\Attachments\UploadAttachmentData;
use Domain\Conversation\Models\Attachment;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Persist a visitor- or member-uploaded attachment to the public Scaleway
 * bucket and create the matching {@see Attachment} row. The DB row
 * persists immediately whether or not the conversation already exists —
 * the Home.vue path uploads first and {@see CreateConversationAction}
 * claims orphan rows by external_id when the conversation is created.
 */
class StoreAttachmentAction
{
    public function execute(UploadAttachmentData $data): Attachment
    {
        // Generate the storage filename from a fresh UUID. The model's
        // external_id is auto-filled by SupportsExternalId on `creating`,
        // and it's intentionally not on Fillable — we want callers using
        // ::create() to never overwrite it.
        $extension = strtolower($data->file->getClientOriginalExtension() ?: 'pdf');
        $directory = trim($data->directory, '/');
        $filename = (string) Str::uuid().'.'.$extension;
        $filepath = $directory.'/'.$filename;

        Storage::disk(Attachment::DISK)
            ->putFileAs($directory, $data->file, $filename, ['visibility' => 'public']);

        return Attachment::create([
            'chat_conversation_id' => $data->conversationId,
            'filepath' => $filepath,
            'filename' => $data->file->getClientOriginalName(),
            'mime_type' => $data->file->getMimeType() ?? 'application/octet-stream',
            'size_bytes' => $data->file->getSize() ?: 0,
        ]);
    }
}

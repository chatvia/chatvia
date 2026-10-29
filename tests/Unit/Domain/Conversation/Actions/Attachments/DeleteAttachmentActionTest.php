<?php

use Domain\Conversation\Actions\Attachments\DeleteAttachmentAction;
use Domain\Conversation\Models\Attachment;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Message;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * The row-locked delete of a composer upload refuses, changing nothing, when
 * the upload was sent with a message in the meantime. Who may delete is
 * DeleteAttachmentRequest's rule (see AttachmentDeleteControllerTest).
 */
it('refuses and keeps the row and file when the upload was sent between authorize and execute', function (): void {
    Storage::fake(Attachment::DISK);
    $attachment = Attachment::factory()->forConversation(Conversation::factory()->hostedChat()->create())->stored()->create();
    $message = Message::factory()->fromUser()->create([
        'chat_conversation_id' => $attachment->chat_conversation_id,
        'content' => 'sent before delete arrived',
    ]);
    DB::table('chat_attachments')
        ->where('id', $attachment->id)
        ->update(['chat_message_id' => $message->id]);

    $deleted = new DeleteAttachmentAction()->execute($attachment);

    expect($deleted)->toBeFalse()
        ->and(Attachment::query()->whereKey($attachment->id)->exists())->toBeTrue();
    Storage::disk(Attachment::DISK)->assertExists($attachment->filepath);
});

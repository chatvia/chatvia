<?php

use Domain\Conversation\Actions\Attachments\DeleteAttachmentAction;
use Domain\Conversation\Models\Attachment;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Message;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * The row-locked delete of a composer upload: it deletes the row and its
 * stored file, and refuses (changing nothing) when the upload was sent with a
 * message in the meantime. Who may delete is DeleteAttachmentRequest's rule
 * (see AttachmentDeleteControllerTest).
 */
beforeEach(function (): void {
    Storage::fake(Attachment::DISK);
    $this->action = new DeleteAttachmentAction;
});

/** A stored upload of a hosted-chat conversation, not yet sent with a message. */
function daaAttachment(): Attachment
{
    return Attachment::factory()->forConversation(Conversation::factory()->hostedChat()->create())->stored()->create();
}

it('deletes the row and wipes the S3 object when the attachment is composer-stage', function (): void {
    $attachment = daaAttachment();

    expect($this->action->execute($attachment))->toBeTrue();

    expect(Attachment::find($attachment->id))->toBeNull();
    Storage::disk(Attachment::DISK)->assertMissing($attachment->filepath);
});

it('returns false and leaves the row intact when the attachment was bound between authorize and execute', function (): void {
    $attachment = daaAttachment();
    $message = Message::factory()->fromUser()->create([
        'chat_conversation_id' => $attachment->chat_conversation_id,
        'content' => 'sent before delete arrived',
    ]);
    // The message sweep wins the race and binds the upload first.
    DB::table('chat_attachments')
        ->where('id', $attachment->id)
        ->update(['chat_message_id' => $message->id]);

    expect($this->action->execute($attachment))->toBeFalse();

    expect(Attachment::find($attachment->id))->not->toBeNull();
    Storage::disk(Attachment::DISK)->assertExists($attachment->filepath);
});

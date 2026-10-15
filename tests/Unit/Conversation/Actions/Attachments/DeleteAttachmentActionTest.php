<?php

use App\Identity\Workspace\Models\User;
use Domain\Conversation\Actions\Attachments\DeleteAttachmentAction;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Models\Attachment;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Message;
use Domain\Conversation\Models\Session;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Unit coverage for the row-locked delete. The HTTP-level authorization
 * lives in DeleteAttachmentRequest (covered by AttachmentDeleteTest); here
 * we prove the Action returns true/false correctly and that the stored file
 * goes with a real delete.
 */
beforeEach(function (): void {
    Storage::fake(Attachment::DISK);
    $this->action = new DeleteAttachmentAction;
});

/** A stored upload of a hosted-chat conversation, not yet sent with a message. */
function dlaAttachment(): Attachment
{
    $agent = createAgent(User::factory()->create(), ['is_public' => true]);
    $conversation = Conversation::factory()->withTitle('Test')->create([
        'chat_session_id' => Session::factory()->create(['agent_id' => $agent->id])->id,
        'source' => ConversationSource::HostedChat,
        'is_visible' => true,
    ]);

    $filepath = sprintf('users/%s/agents/%s/attachments/%s/%s.pdf', $agent->workspaceExternalId(), $agent->external_id, $conversation->external_id, Str::uuid());
    Storage::disk(Attachment::DISK)->put($filepath, 'fake pdf');

    return Attachment::create([
        'chat_conversation_id' => $conversation->id,
        'filepath' => $filepath,
        'filename' => 'job-posting.pdf',
        'mime_type' => 'application/pdf',
        'size_bytes' => 8,
    ]);
}

it('deletes the row and wipes the S3 object when the attachment is composer-stage', function (): void {
    $attachment = dlaAttachment();

    expect($this->action->execute($attachment))->toBeTrue();

    expect(Attachment::find($attachment->id))->toBeNull();
    Storage::disk(Attachment::DISK)->assertMissing($attachment->filepath);
});

it('returns false and leaves the row intact when the attachment was bound between authorize and execute', function (): void {
    $attachment = dlaAttachment();
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

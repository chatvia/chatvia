<?php

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Actions\Attachments\DeleteAttachmentAction;
use Domain\Conversation\Models\Attachment;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Message;
use Domain\Conversation\Models\Session;
use Domain\Membership\Team\Services\TeamService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Unit coverage for the row-locked delete. The HTTP-level authorization
 * lives in DeleteAttachmentRequest (covered by AttachmentDeleteTest); here
 * we prove the Action returns true/false correctly and that the model's S3
 * cleanup hook fires on a real delete.
 */
beforeEach(function (): void {
    Storage::fake('public');
    $this->action = new DeleteAttachmentAction;
});

function buildAttachment(?int $messageId = null): Attachment
{
    $owner = User::factory()->create();
    app(TeamService::class)->createDefaultTeam($owner);
    $agent = Agent::create([
        'user_id' => $owner->id,
        'team_id' => $owner->primaryTeam()->id,
        'name' => 'Bot',
        'external_id' => Str::random(14),
        'sub_domain' => Str::lower(Str::random(12)),
        'is_public' => true,
    ]);
    $session = Session::create(['agent_id' => $agent->id]);
    $conversation = Conversation::create([
        'chat_session_id' => $session->id,
        'source' => 'hosted_chat',
        'is_visible' => true,
        'title' => 'Test',
    ]);

    $filepath = sprintf('chat-uploads/agent-%d/%s/%s.pdf', $agent->id, $conversation->external_id, Str::uuid());
    Storage::disk('public')->put($filepath, 'fake pdf');

    return Attachment::create([
        'chat_conversation_id' => $conversation->id,
        'chat_message_id' => $messageId,
        'filepath' => $filepath,
        'filename' => 'job-posting.pdf',
        'mime_type' => 'application/pdf',
        'size_bytes' => 8,
    ]);
}

it('deletes the row and wipes the S3 object when the attachment is composer-stage', function (): void {
    $attachment = buildAttachment();

    expect($this->action->execute($attachment))->toBeTrue();

    expect(Attachment::find($attachment->id))->toBeNull();
    Storage::disk('public')->assertMissing($attachment->filepath);
});

it('returns false and leaves the row intact when the attachment was bound between authorize and execute', function (): void {
    $attachment = buildAttachment();
    $message = Message::create([
        'chat_conversation_id' => $attachment->chat_conversation_id,
        'role' => 'user',
        'content' => 'sent before delete arrived',
    ]);
    // Simulate the StoreMessageAction sweep winning the race.
    DB::table('chat_attachments')
        ->where('id', $attachment->id)
        ->update(['chat_message_id' => $message->id]);

    expect($this->action->execute($attachment))->toBeFalse();

    expect(Attachment::find($attachment->id))->not->toBeNull();
    Storage::disk('public')->assertExists($attachment->filepath);
});

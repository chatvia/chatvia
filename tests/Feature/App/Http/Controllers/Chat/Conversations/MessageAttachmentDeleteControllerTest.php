<?php

use App\Identity\Chatter\Models\Chatter;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Data\Memory\ChatViewerData;
use Domain\Conversation\Models\Attachment;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Message;
use Domain\Conversation\Models\Session;
use Domain\Conversation\Services\Memory\ChatCache;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Conversation\HostedChatVisitor;

/**
 * Deleting the file of a sent message, with its stored copy. The conversation
 * must be in the caller's own chat session of this agent, on a public agent
 * too; the file must belong to the conversation the address names.
 */
beforeEach(function (): void {
    Storage::fake(Attachment::DISK);
});

/** A sent message's stored file, in a hosted-chat conversation of the session. */
function madcAttachment(Session $session): Attachment
{
    $conversation = Conversation::factory()->inSession($session)->hostedChat()->create();

    return madcAttachmentIn($conversation);
}

/** A sent message's stored file in the given conversation. */
function madcAttachmentIn(Conversation $conversation): Attachment
{
    $message = Message::factory()->fromUser()->create(['chat_conversation_id' => $conversation->id]);

    return Attachment::factory()->forMessage($message)->stored()->create();
}

/** A sent message's stored file in a conversation of this test's browser session. */
function madcOwnAttachment(Agent $agent): Attachment
{
    $conversationId = HostedChatVisitor::startConversation($agent);

    return madcAttachmentIn(Conversation::query()->where('external_id', $conversationId)->sole());
}

function madcUrl(Agent $agent, Attachment $attachment, ?Conversation $conversation = null): string
{
    $conversationId = ($conversation ?? $attachment->conversation)->external_id;

    return "/chat/{$agent->external_id}/conversations/{$conversationId}/attachments/{$attachment->external_id}";
}

it('deletes a sent message\'s file on a public agent, keeping the message', function (): void {
    $agent = Agent::factory()->create(['is_public' => true]);
    $attachment = madcOwnAttachment($agent);

    $this->delete(madcUrl($agent, $attachment))->assertNoContent();

    expect(Attachment::find($attachment->id))->toBeNull()
        // The Message survives the cascade (chat_message_id is nullOnDelete).
        ->and(Message::find($attachment->chat_message_id))->not->toBeNull();
    Storage::disk(Attachment::DISK)->assertMissing($attachment->filepath);
});

it('answers 404 for another visitor\'s file on a public agent and keeps it', function (): void {
    $agent = Agent::factory()->create(['is_public' => true]);
    $attachment = madcAttachment(Session::factory()->create(['agent_id' => $agent->id]));

    $this->delete(madcUrl($agent, $attachment))->assertNotFound();

    expect(Attachment::find($attachment->id))->not->toBeNull();
    Storage::disk(Attachment::DISK)->assertExists($attachment->filepath);
});

it('answers 404 when the address names another conversation than the file\'s', function (): void {
    $agent = Agent::factory()->create(['is_public' => true]);
    $attachment = madcOwnAttachment($agent);
    $otherConversation = Conversation::query()
        ->where('external_id', HostedChatVisitor::startConversation($agent))
        ->sole();

    $this->delete(madcUrl($agent, $attachment, $otherConversation))->assertNotFound();

    expect(Attachment::find($attachment->id))->not->toBeNull();
    Storage::disk(Attachment::DISK)->assertExists($attachment->filepath);
});

it('answers 404 on a private agent when the requester does not own the conversation', function (): void {
    $agent = Agent::factory()->create(['is_public' => false]);
    $owner = Chatter::factory()->grantedTo($agent)->create();
    $intruder = Chatter::factory()->grantedTo($agent)->create();
    $attachment = madcAttachment(Session::factory()->forChatter($owner)->create(['agent_id' => $agent->id]));
    ChatCache::rememberOwner($attachment->conversation->external_id, ChatViewerData::chatter($owner->id), $agent);

    $this->actingAs($intruder, 'chat')->delete(madcUrl($agent, $attachment))->assertNotFound();

    expect(Attachment::find($attachment->id))->not->toBeNull();
});

it('lets the chatter who owns the conversation delete on a private agent', function (): void {
    $agent = Agent::factory()->create(['is_public' => false]);
    $owner = Chatter::factory()->grantedTo($agent)->create();
    $attachment = madcAttachment(Session::factory()->forChatter($owner)->create(['agent_id' => $agent->id]));
    ChatCache::rememberOwner($attachment->conversation->external_id, ChatViewerData::chatter($owner->id), $agent);

    $this->actingAs($owner, 'chat')->delete(madcUrl($agent, $attachment))->assertNoContent();

    expect(Attachment::find($attachment->id))->toBeNull();
    Storage::disk(Attachment::DISK)->assertMissing($attachment->filepath);
});

it('lets a staff member delete on a private agent they opened from the dashboard', function (): void {
    $agent = Agent::factory()->create(['is_public' => false]);
    $staff = $agent->workspace->owner;
    $attachment = madcAttachment(Session::factory()->forUser($staff)->create(['agent_id' => $agent->id]));
    ChatCache::rememberOwner($attachment->conversation->external_id, ChatViewerData::staff($staff->id), $agent);

    $this->actingAs($staff, 'chat_staff')->delete(madcUrl($agent, $attachment))->assertNoContent();

    expect(Attachment::find($attachment->id))->toBeNull();
});

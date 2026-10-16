<?php

use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Data\Memory\ChatViewerData;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Models\Attachment;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Message;
use Domain\Conversation\Models\Session;
use Domain\Conversation\Services\Memory\ChatCache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * HTTP coverage for the sent-message attachment delete endpoint. This is the
 * companion to the composer-stage delete (AttachmentDeleteTest): same
 * underlying Attachment model + S3 hook, but a different route and
 * authorization model — the route binds {conversationId} so the chat
 * middleware enforces ChatCache::isOwnedBy() on protected agents, and the
 * controller defensively cross-checks the URL's conversation against the
 * row's conversation + the current agent.
 */
/** A hosted-chat conversation of the agent, in a session of the chatter or staff account (or of a visitor). */
function messageConversation(Agent $agent, Chatter|User|null $viewer = null): Conversation
{
    $session = Session::factory()->create([
        'agent_id' => $agent->id,
        'chatter_id' => $viewer instanceof Chatter ? $viewer->id : null,
        'user_id' => $viewer instanceof User ? $viewer->id : null,
    ]);

    return Conversation::factory()->create([
        'chat_session_id' => $session->id,
        'source' => ConversationSource::HostedChat,
        'title' => 'Test',
    ]);
}

function messageAttachment(Agent $agent, Conversation $conversation, Message $message): Attachment
{
    $filepath = sprintf('users/%s/agents/%s/attachments/%s/%s.pdf', $agent->workspaceExternalId(), $agent->external_id, $conversation->external_id, Str::uuid());
    Storage::disk(Attachment::DISK)->put($filepath, 'fake');

    return Attachment::create([
        'chat_conversation_id' => $conversation->id,
        'chat_message_id' => $message->id,
        'filepath' => $filepath,
        'filename' => 'job-posting.pdf',
        'mime_type' => 'application/pdf',
        'size_bytes' => 4,
    ]);
}

beforeEach(function (): void {
    Storage::fake(Attachment::DISK);
});

it('deletes a sent-message attachment on a public agent and wipes the S3 object', function (): void {
    $agent = Agent::factory()->create(['is_public' => true]);
    $conversation = messageConversation($agent);
    $message = Message::factory()->fromUser()->create(['chat_conversation_id' => $conversation->id, 'content' => 'see attached']);
    $attachment = messageAttachment($agent, $conversation, $message);

    $response = $this->delete(
        "/chat/{$agent->external_id}/conversations/{$conversation->external_id}/attachments/{$attachment->external_id}",
    );

    $response->assertNoContent();
    expect(Attachment::find($attachment->id))->toBeNull();
    Storage::disk(Attachment::DISK)->assertMissing($attachment->filepath);
    // The Message survives the cascade (chat_message_id is nullOnDelete).
    expect(Message::find($message->id))->not->toBeNull();
});

it('refuses when the URL conversation id does not match the attachment row', function (): void {
    $agent = Agent::factory()->create(['is_public' => true]);
    $conversation = messageConversation($agent);
    $otherConversation = messageConversation($agent);
    $message = Message::factory()->fromUser()->create(['chat_conversation_id' => $conversation->id, 'content' => '...']);
    $attachment = messageAttachment($agent, $conversation, $message);

    $response = $this->delete(
        "/chat/{$agent->external_id}/conversations/{$otherConversation->external_id}/attachments/{$attachment->external_id}",
    );

    $response->assertForbidden();
    expect(Attachment::find($attachment->id))->not->toBeNull();
    Storage::disk(Attachment::DISK)->assertExists($attachment->filepath);
});

it('answers 404 on a private agent when the requester does not own the conversation', function (): void {
    $agent = Agent::factory()->create(['is_public' => false]);
    $owner = Chatter::factory()->grantedTo($agent)->create();
    $intruder = Chatter::factory()->grantedTo($agent)->create();

    $conversation = messageConversation($agent, $owner);
    ChatCache::rememberOwner($conversation->external_id, ChatViewerData::chatter($owner->id), $agent->workspaceExternalId());
    $message = Message::factory()->fromUser()->create(['chat_conversation_id' => $conversation->id, 'content' => '...']);
    $attachment = messageAttachment($agent, $conversation, $message);

    $response = $this->actingAs($intruder, 'chat')->delete(
        "/chat/{$agent->external_id}/conversations/{$conversation->external_id}/attachments/{$attachment->external_id}",
    );

    $response->assertNotFound();
    expect(Attachment::find($attachment->id))->not->toBeNull();
});

it('lets the rightful owner delete on a private agent', function (): void {
    $agent = Agent::factory()->create(['is_public' => false]);
    $owner = Chatter::factory()->grantedTo($agent)->create();

    $conversation = messageConversation($agent, $owner);
    ChatCache::rememberOwner($conversation->external_id, ChatViewerData::chatter($owner->id), $agent->workspaceExternalId());
    $message = Message::factory()->fromUser()->create(['chat_conversation_id' => $conversation->id, 'content' => '...']);
    $attachment = messageAttachment($agent, $conversation, $message);

    $response = $this->actingAs($owner, 'chat')->delete(
        "/chat/{$agent->external_id}/conversations/{$conversation->external_id}/attachments/{$attachment->external_id}",
    );

    $response->assertNoContent();
    expect(Attachment::find($attachment->id))->toBeNull();
    Storage::disk(Attachment::DISK)->assertMissing($attachment->filepath);
});

it('lets a staff member delete on a private agent they opened from the dashboard', function (): void {
    $agent = Agent::factory()->create(['is_public' => false]);
    $staff = $agent->workspace->owner;

    $conversation = messageConversation($agent, $staff);
    ChatCache::rememberOwner($conversation->external_id, ChatViewerData::staff($staff->id), $agent->workspaceExternalId());
    $message = Message::factory()->fromUser()->create(['chat_conversation_id' => $conversation->id, 'content' => '...']);
    $attachment = messageAttachment($agent, $conversation, $message);

    $response = $this->actingAs($staff, 'chat_staff')->delete(
        "/chat/{$agent->external_id}/conversations/{$conversation->external_id}/attachments/{$attachment->external_id}",
    );

    $response->assertNoContent();
    expect(Attachment::find($attachment->id))->toBeNull();
});

it('returns 404 for a non-existent attachment external_id', function (): void {
    $agent = Agent::factory()->create(['is_public' => true]);
    $conversation = messageConversation($agent);

    $response = $this->delete(
        "/chat/{$agent->external_id}/conversations/{$conversation->external_id}/attachments/01999999-0000-7000-8000-000000000000",
    );

    $response->assertNotFound();
});

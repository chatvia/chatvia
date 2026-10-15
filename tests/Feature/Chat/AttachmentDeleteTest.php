<?php

use App\Identity\Chatter\Actions\CreateChatterAction;
use App\Identity\Chatter\Data\CreateChatterData;
use App\Identity\Chatter\Models\Chatter;
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
 * HTTP coverage for the composer-X delete endpoint. Proves that clicking the
 * remove chip in the composer wipes both the DB row AND the stored file —
 * plus the negative cases that keep the route from becoming a
 * delete-anyone's-file hole.
 */
function adtAttachment(Agent $agent, ?Conversation $conversation = null, ?int $messageId = null): Attachment
{
    $convPart = $conversation instanceof Conversation ? $conversation->external_id : 'pending';
    $uuid = (string) Str::uuid();
    $filepath = sprintf('workspaces/%s/agents/%s/attachments/%s/%s.pdf', $agent->workspaceExternalId(), $agent->external_id, $convPart, $uuid);

    Storage::disk(Attachment::DISK)->put($filepath, 'fake pdf bytes');

    return Attachment::create([
        'chat_conversation_id' => $conversation?->id,
        'chat_message_id' => $messageId,
        'filepath' => $filepath,
        'filename' => 'job-posting.pdf',
        'mime_type' => 'application/pdf',
        'size_bytes' => 14,
    ]);
}

/** A hosted-chat conversation of the agent, in a session of the chatter (or of a visitor). */
function adtConversation(Agent $agent, ?Chatter $chatter = null): Conversation
{
    return Conversation::factory()->create([
        'chat_session_id' => Session::factory()->create(['agent_id' => $agent->id, 'chatter_id' => $chatter?->id])->id,
        'source' => ConversationSource::HostedChat,
        'title' => 'Test chat',
    ]);
}

beforeEach(function (): void {
    Storage::fake(Attachment::DISK);
});

it('deletes an orphan attachment (Home.vue pending flow) and wipes its S3 object', function (): void {
    $agent = Agent::factory()->create(['is_public' => true]);
    $attachment = adtAttachment($agent);

    Storage::disk(Attachment::DISK)->assertExists($attachment->filepath);

    $response = $this->delete(
        "/chat/{$agent->external_id}/messages/attachments/{$attachment->external_id}",
    );

    $response->assertNoContent();
    expect(Attachment::find($attachment->id))->toBeNull();
    Storage::disk(Attachment::DISK)->assertMissing($attachment->filepath);
});

it('deletes a conversation-bound attachment on a public agent and wipes its S3 object', function (): void {
    $agent = Agent::factory()->create(['is_public' => true]);
    $conversation = adtConversation($agent);
    $attachment = adtAttachment($agent, $conversation);

    $response = $this->delete(
        "/chat/{$agent->external_id}/messages/attachments/{$attachment->external_id}",
    );

    $response->assertNoContent();
    expect(Attachment::find($attachment->id))->toBeNull();
    Storage::disk(Attachment::DISK)->assertMissing($attachment->filepath);
});

it('refuses to delete an attachment already bound to a sent message (composer-only guard)', function (): void {
    $agent = Agent::factory()->create(['is_public' => true]);
    $conversation = adtConversation($agent);
    $message = Message::factory()->fromUser()->create(['chat_conversation_id' => $conversation->id, 'content' => 'see attached']);
    $attachment = adtAttachment($agent, $conversation, messageId: $message->id);

    $response = $this->delete(
        "/chat/{$agent->external_id}/messages/attachments/{$attachment->external_id}",
    );

    $response->assertForbidden();
    expect(Attachment::find($attachment->id))->not->toBeNull();
    Storage::disk(Attachment::DISK)->assertExists($attachment->filepath);
});

it('answers 404 for an orphan attachment of another workspace\'s agent', function (): void {
    $otherAgent = Agent::factory()->create(['is_public' => true]);
    $attachment = adtAttachment($otherAgent); // filepath baked under $otherAgent->external_id

    // Hit the endpoint as an agent of another workspace.
    $requesterAgent = Agent::factory()->create(['is_public' => true]);

    $response = $this->delete(
        "/chat/{$requesterAgent->external_id}/messages/attachments/{$attachment->external_id}",
    );

    $response->assertNotFound();
    expect(Attachment::find($attachment->id))->not->toBeNull();
    Storage::disk(Attachment::DISK)->assertExists($attachment->filepath);
});

it('answers 404 for a conversation-bound attachment of another workspace\'s agent', function (bool $sent): void {
    $otherAgent = Agent::factory()->create(['is_public' => true]);
    $conversation = adtConversation($otherAgent);
    $messageId = $sent ? Message::factory()->fromUser()->create(['chat_conversation_id' => $conversation->id])->id : null;
    $attachment = adtAttachment($otherAgent, $conversation, $messageId);

    $requesterAgent = Agent::factory()->create(['is_public' => true]);

    $response = $this->delete(
        "/chat/{$requesterAgent->external_id}/messages/attachments/{$attachment->external_id}",
    );

    $response->assertNotFound();
    expect(Attachment::find($attachment->id))->not->toBeNull();
    Storage::disk(Attachment::DISK)->assertExists($attachment->filepath);
})->with([
    'in the composer' => false,
    'on a sent message' => true,
]);

it('refuses to delete on a private agent when the auth user does not own the conversation', function (): void {
    $agent = Agent::factory()->create(['is_public' => false]);
    $owner = Chatter::factory()->grantedTo($agent)->create();

    // The intruder IS a legitimate chatter of this agent (agent_chatter grant),
    // so EnsureChatIsAccessible's membership gate lets them through — the
    // per-conversation ownership check in DeleteAttachmentRequest is what
    // must refuse them.
    // refresh(): CreateChatterAction returns the in-memory Chatter::create()
    // instance, which lacks the columns it did not set (locale, avatar path…)
    // that the chat reads under strict mode.
    $intruder = app(CreateChatterAction::class)->execute(new CreateChatterData($agent->workspace, $agent, 'Intruder'))->refresh();

    $conversation = adtConversation($agent, $owner);
    ChatCache::rememberOwner($conversation->external_id, ChatViewerData::chatter($owner->id), $agent->workspaceExternalId());

    $attachment = adtAttachment($agent, $conversation);

    $response = $this->actingAs($intruder, 'chat')->delete(
        "/chat/{$agent->external_id}/messages/attachments/{$attachment->external_id}",
    );

    $response->assertForbidden();
    expect(Attachment::find($attachment->id))->not->toBeNull();
    Storage::disk(Attachment::DISK)->assertExists($attachment->filepath);
});

it('lets the rightful owner delete on a private agent', function (): void {
    $agent = Agent::factory()->create(['is_public' => false]);

    // This route carries no {conversationId}, so EnsureChatIsAccessible
    // gates it on agent membership (canAccessAgent) — the owner must be a
    // provisioned chatter of the agent, not a bare factory user.
    $owner = app(CreateChatterAction::class)->execute(new CreateChatterData($agent->workspace, $agent, 'Owner'))->refresh();

    $conversation = adtConversation($agent, $owner);
    ChatCache::rememberOwner($conversation->external_id, ChatViewerData::chatter($owner->id), $agent->workspaceExternalId());

    $attachment = adtAttachment($agent, $conversation);

    $response = $this->actingAs($owner, 'chat')->delete(
        "/chat/{$agent->external_id}/messages/attachments/{$attachment->external_id}",
    );

    $response->assertNoContent();
    expect(Attachment::find($attachment->id))->toBeNull();
    Storage::disk(Attachment::DISK)->assertMissing($attachment->filepath);
});

it('returns 404 for a non-existent attachment external_id (route-model binding)', function (): void {
    $agent = Agent::factory()->create(['is_public' => true]);

    $response = $this->delete(
        "/chat/{$agent->external_id}/messages/attachments/01999999-0000-7000-8000-000000000000",
    );

    $response->assertNotFound();
});

it('refuses to delete an attachment whose conversation has been soft-deleted', function (): void {
    $agent = Agent::factory()->create(['is_public' => true]);
    $conversation = adtConversation($agent);
    $attachment = adtAttachment($agent, $conversation);

    // The Conversation::booted() deleting hook normally cascades, but a partial
    // failure mid-cascade could leave an attachment row pointing at a trashed
    // conversation. The controller still refuses (Attachment->conversation()
    // applies SoftDeletes scope → null → 403); the daily prune is the only
    // path that cleans up such an orphan. Lock the behaviour in.
    $conversation->forceFill(['deleted_at' => now()])->saveQuietly();

    $response = $this->delete(
        "/chat/{$agent->external_id}/messages/attachments/{$attachment->external_id}",
    );

    $response->assertForbidden();
});

<?php

use App\Identity\Chatter\Actions\CreateChatterAction;
use App\Identity\Chatter\Data\CreateChatterData;
use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Data\Memory\ChatViewerData;
use Domain\Conversation\Models\Attachment;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Message;
use Domain\Conversation\Models\Session;
use Domain\Conversation\Services\Memory\ChatCache;
use Domain\Membership\Team\Services\TeamService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * HTTP coverage for the composer-X delete endpoint. Proves that clicking the
 * remove chip in the composer wipes both the DB row AND the S3 object on the
 * public disk — plus the negative cases that keep the route from becoming a
 * delete-anyone's-file hole.
 */
function attachmentDeleteAgent(bool $isPublic = true): Agent
{
    $owner = User::factory()->create();
    app(TeamService::class)->createDefaultTeam($owner);

    $owner->refresh();

    return Agent::create([
        'workspace_id' => $owner->workspace()->id,
        'team_id' => $owner->primaryTeam()->id,
        'name' => 'Attachment Bot',
        'external_id' => Str::random(14),
        'slug' => Str::lower(Str::random(12)),
        'system_instructions' => 'You are a helpful assistant.',
        'is_public' => $isPublic,
    ]);
}

function makeAttachment(Agent $agent, ?Conversation $conversation = null, ?int $messageId = null): Attachment
{
    $convPart = $conversation instanceof Conversation ? $conversation->external_id : 'pending';
    $uuid = (string) Str::uuid();
    $filepath = sprintf('workspaces/%s/agents/%s/attachments/%s/%s.pdf', $agent->workspaceExternalId(), $agent->external_id, $convPart, $uuid);

    Storage::disk('public')->put($filepath, 'fake pdf bytes');

    return Attachment::create([
        'chat_conversation_id' => $conversation?->id,
        'chat_message_id' => $messageId,
        'filepath' => $filepath,
        'filename' => 'job-posting.pdf',
        'mime_type' => 'application/pdf',
        'size_bytes' => 14,
    ]);
}

function makeConversation(Agent $agent, ?Chatter $chatter = null): Conversation
{
    $session = Session::create([
        'workspace_id' => $agent->workspace_id,
        'agent_id' => $agent->id,
        'chatter_id' => $chatter?->id,
    ]);

    return Conversation::create([
        'workspace_id' => $session->workspace_id,
        'chat_session_id' => $session->id,
        'agent_id' => $session->agent_id,
        'source' => 'hosted_chat',
        'is_visible' => true,
        'title' => 'Test chat',
    ]);
}

beforeEach(function (): void {
    Storage::fake('public');
});

it('deletes an orphan attachment (Home.vue pending flow) and wipes its S3 object', function (): void {
    $agent = attachmentDeleteAgent();
    $attachment = makeAttachment($agent);

    Storage::disk('public')->assertExists($attachment->filepath);

    $response = $this->delete(
        "/chat/{$agent->external_id}/messages/attachments/{$attachment->external_id}",
    );

    $response->assertNoContent();
    expect(Attachment::find($attachment->id))->toBeNull();
    Storage::disk('public')->assertMissing($attachment->filepath);
});

it('deletes a conversation-bound attachment on a public agent and wipes its S3 object', function (): void {
    $agent = attachmentDeleteAgent();
    $conversation = makeConversation($agent);
    $attachment = makeAttachment($agent, $conversation);

    $response = $this->delete(
        "/chat/{$agent->external_id}/messages/attachments/{$attachment->external_id}",
    );

    $response->assertNoContent();
    expect(Attachment::find($attachment->id))->toBeNull();
    Storage::disk('public')->assertMissing($attachment->filepath);
});

it('refuses to delete an attachment already bound to a sent message (composer-only guard)', function (): void {
    $agent = attachmentDeleteAgent();
    $conversation = makeConversation($agent);
    $message = Message::create([
        'workspace_id' => $conversation->workspace_id,
        'chat_conversation_id' => $conversation->id,
        'role' => 'user',
        'content' => 'see attached',
    ]);
    $attachment = makeAttachment($agent, $conversation, messageId: $message->id);

    $response = $this->delete(
        "/chat/{$agent->external_id}/messages/attachments/{$attachment->external_id}",
    );

    $response->assertForbidden();
    expect(Attachment::find($attachment->id))->not->toBeNull();
    Storage::disk('public')->assertExists($attachment->filepath);
});

it('refuses to delete an orphan attachment that lives under a different agent\'s upload bucket', function (): void {
    $otherAgent = attachmentDeleteAgent();
    $attachment = makeAttachment($otherAgent); // filepath baked under $otherAgent->external_id

    // Hit the endpoint as a different agent.
    $requesterAgent = attachmentDeleteAgent();

    $response = $this->delete(
        "/chat/{$requesterAgent->external_id}/messages/attachments/{$attachment->external_id}",
    );

    $response->assertForbidden();
    expect(Attachment::find($attachment->id))->not->toBeNull();
    Storage::disk('public')->assertExists($attachment->filepath);
});

it('refuses to delete a conversation-bound attachment whose conversation belongs to another agent', function (): void {
    $otherAgent = attachmentDeleteAgent();
    $conversation = makeConversation($otherAgent);
    $attachment = makeAttachment($otherAgent, $conversation);

    $requesterAgent = attachmentDeleteAgent();

    $response = $this->delete(
        "/chat/{$requesterAgent->external_id}/messages/attachments/{$attachment->external_id}",
    );

    $response->assertForbidden();
    expect(Attachment::find($attachment->id))->not->toBeNull();
    Storage::disk('public')->assertExists($attachment->filepath);
});

it('refuses to delete on a private agent when the auth user does not own the conversation', function (): void {
    $agent = attachmentDeleteAgent(isPublic: false);
    $owner = Chatter::factory()->grantedTo($agent)->create();

    // The intruder IS a legitimate chatter of this agent (agent_chatter grant),
    // so EnsureChatIsAccessible's membership gate lets them through — the
    // per-conversation ownership check in DeleteAttachmentRequest is what
    // must refuse them.
    // refresh(): CreateChatterAction returns the in-memory Chatter::create()
    // instance, which lacks the columns it did not set (locale, avatar path…)
    // that the chat reads under strict mode.
    $intruder = app(CreateChatterAction::class)->execute(new CreateChatterData($agent->workspace, $agent, 'Intruder'))->refresh();

    $conversation = makeConversation($agent, $owner);
    ChatCache::rememberOwner($conversation->external_id, ChatViewerData::chatter($owner->id), $agent->workspaceExternalId());

    $attachment = makeAttachment($agent, $conversation);

    $response = $this->actingAs($intruder, 'chat')->delete(
        "/chat/{$agent->external_id}/messages/attachments/{$attachment->external_id}",
    );

    $response->assertForbidden();
    expect(Attachment::find($attachment->id))->not->toBeNull();
    Storage::disk('public')->assertExists($attachment->filepath);
});

it('lets the rightful owner delete on a private agent', function (): void {
    $agent = attachmentDeleteAgent(isPublic: false);

    // This route carries no {conversationId}, so EnsureChatIsAccessible
    // gates it on agent membership (canAccessAgent) — the owner must be a
    // provisioned chatter of the agent, not a bare factory user.
    $owner = app(CreateChatterAction::class)->execute(new CreateChatterData($agent->workspace, $agent, 'Owner'))->refresh();

    $conversation = makeConversation($agent, $owner);
    ChatCache::rememberOwner($conversation->external_id, ChatViewerData::chatter($owner->id), $agent->workspaceExternalId());

    $attachment = makeAttachment($agent, $conversation);

    $response = $this->actingAs($owner, 'chat')->delete(
        "/chat/{$agent->external_id}/messages/attachments/{$attachment->external_id}",
    );

    $response->assertNoContent();
    expect(Attachment::find($attachment->id))->toBeNull();
    Storage::disk('public')->assertMissing($attachment->filepath);
});

it('returns 404 for a non-existent attachment external_id (route-model binding)', function (): void {
    $agent = attachmentDeleteAgent();

    $response = $this->delete(
        "/chat/{$agent->external_id}/messages/attachments/01999999-0000-7000-8000-000000000000",
    );

    $response->assertNotFound();
});

it('refuses to delete an attachment whose conversation has been soft-deleted', function (): void {
    $agent = attachmentDeleteAgent();
    $conversation = makeConversation($agent);
    $attachment = makeAttachment($agent, $conversation);

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

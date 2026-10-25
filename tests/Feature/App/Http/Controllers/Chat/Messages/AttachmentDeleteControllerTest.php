<?php

use App\Identity\Chatter\Actions\CreateChatterAction;
use App\Identity\Chatter\Data\CreateChatterData;
use App\Identity\Chatter\Models\Chatter;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Data\Memory\ChatViewerData;
use Domain\Conversation\Models\Attachment;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Message;
use Domain\Conversation\Services\Memory\ChatCache;
use Illuminate\Support\Facades\Storage;

/**
 * The remove chip in the composer deletes an upload's row and its stored
 * file. A file already sent with a message, another workspace's file, a file
 * in someone else's conversation on a protected agent, and a file whose
 * conversation is gone are all refused and kept.
 */
beforeEach(function (): void {
    Storage::fake(Attachment::DISK);
});

/** A hosted-chat conversation of the agent, in a session of the chatter (or of a visitor). */
function adcConversation(Agent $agent, ?Chatter $chatter = null): Conversation
{
    $factory = Conversation::factory()->forAgent($agent)->hostedChat();

    return ($chatter instanceof Chatter ? $factory->ofChatter($chatter) : $factory)->create();
}

function adcUrl(Agent $agent, Attachment $attachment): string
{
    return "/chat/{$agent->external_id}/messages/attachments/{$attachment->external_id}";
}

function adcAssertKept(Attachment $attachment): void
{
    expect(Attachment::find($attachment->id))->not->toBeNull();
    Storage::disk(Attachment::DISK)->assertExists($attachment->filepath);
}

it('deletes an upload and its stored file, before or after the conversation exists', function (Closure $upload): void {
    $agent = Agent::factory()->create(['is_public' => true]);
    $attachment = $upload($agent);

    $this->delete(adcUrl($agent, $attachment))->assertNoContent();

    expect(Attachment::find($attachment->id))->toBeNull();
    Storage::disk(Attachment::DISK)->assertMissing($attachment->filepath);
})->with([
    'pending in the composer' => [fn (Agent $agent): Attachment => Attachment::factory()->pending($agent)->stored()->create()],
    'in a conversation' => [fn (Agent $agent): Attachment => Attachment::factory()->forConversation(adcConversation($agent))->stored()->create()],
]);

it('refuses to delete an upload already sent with a message', function (): void {
    $agent = Agent::factory()->create(['is_public' => true]);
    $message = Message::factory()->fromUser()->create(['chat_conversation_id' => adcConversation($agent)->id]);
    $attachment = Attachment::factory()->forMessage($message)->stored()->create();

    $this->delete(adcUrl($agent, $attachment))->assertForbidden();

    adcAssertKept($attachment);
});

it('answers 404 for an upload of another workspace\'s agent', function (Closure $upload): void {
    $attachment = $upload(Agent::factory()->create(['is_public' => true]));
    $requesterAgent = Agent::factory()->create(['is_public' => true]);

    $this->delete(adcUrl($requesterAgent, $attachment))->assertNotFound();

    adcAssertKept($attachment);
})->with([
    'pending in the composer' => [fn (Agent $agent): Attachment => Attachment::factory()->pending($agent)->stored()->create()],
    'in a conversation' => [fn (Agent $agent): Attachment => Attachment::factory()->forConversation(adcConversation($agent))->stored()->create()],
    'on a sent message' => [fn (Agent $agent): Attachment => Attachment::factory()->forMessage(Message::factory()->fromUser()->create(['chat_conversation_id' => adcConversation($agent)->id]))->stored()->create()],
]);

it('refuses to delete on a private agent when the signed-in chatter does not own the conversation', function (): void {
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

    $conversation = adcConversation($agent, $owner);
    ChatCache::rememberOwner($conversation->external_id, ChatViewerData::chatter($owner->id), $agent);
    $attachment = Attachment::factory()->forConversation($conversation)->stored()->create();

    $this->actingAs($intruder, 'chat')->delete(adcUrl($agent, $attachment))->assertForbidden();

    adcAssertKept($attachment);
});

it('lets the rightful owner delete on a private agent', function (): void {
    $agent = Agent::factory()->create(['is_public' => false]);

    // This route carries no {conversationId}, so EnsureChatIsAccessible
    // gates it on agent membership (canAccessAgent) — the owner must be a
    // provisioned chatter of the agent, not a bare factory user.
    $owner = app(CreateChatterAction::class)->execute(new CreateChatterData($agent->workspace, $agent, 'Owner'))->refresh();

    $conversation = adcConversation($agent, $owner);
    ChatCache::rememberOwner($conversation->external_id, ChatViewerData::chatter($owner->id), $agent);
    $attachment = Attachment::factory()->forConversation($conversation)->stored()->create();

    $this->actingAs($owner, 'chat')->delete(adcUrl($agent, $attachment))->assertNoContent();

    expect(Attachment::find($attachment->id))->toBeNull();
    Storage::disk(Attachment::DISK)->assertMissing($attachment->filepath);
});

it('refuses to delete an upload whose conversation has been soft-deleted', function (): void {
    $agent = Agent::factory()->create(['is_public' => true]);
    $conversation = adcConversation($agent);
    $attachment = Attachment::factory()->forConversation($conversation)->stored()->create();

    // The Conversation::booted() deleting hook normally cascades, but a partial
    // failure mid-cascade could leave an attachment row pointing at a trashed
    // conversation. The controller still refuses (Attachment->conversation()
    // applies SoftDeletes scope → null → 403); the daily prune is the only
    // path that cleans up such an orphan. Lock the behaviour in.
    $conversation->forceFill(['deleted_at' => now()])->saveQuietly();

    $this->delete(adcUrl($agent, $attachment))->assertForbidden();
});

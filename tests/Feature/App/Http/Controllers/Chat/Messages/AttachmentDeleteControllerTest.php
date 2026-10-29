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
 * file. A file already sent with a message, another workspace's file and a
 * file in someone else's conversation on a protected agent are all refused
 * and kept.
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

it('refuses and keeps an upload already sent with a message, or in another chatter\'s conversation on a private agent', function (): void {
    $public = Agent::factory()->create(['is_public' => true]);
    $message = Message::factory()->fromUser()->create(['chat_conversation_id' => adcConversation($public)->id]);
    $sent = Attachment::factory()->forMessage($message)->stored()->create();
    $private = Agent::factory()->create(['is_public' => false]);
    $owner = Chatter::factory()->grantedTo($private)->create();
    $intruder = app(CreateChatterAction::class)->execute(new CreateChatterData($private->workspace, $private, 'Intruder'))->refresh();
    $conversation = adcConversation($private, $owner);
    ChatCache::rememberOwner($conversation->external_id, ChatViewerData::chatter($owner->id), $private);
    $theirs = Attachment::factory()->forConversation($conversation)->stored()->create();

    $this->delete(adcUrl($public, $sent))->assertForbidden();
    $this->actingAs($intruder, 'chat')->delete(adcUrl($private, $theirs))->assertForbidden();

    adcAssertKept($sent);
    adcAssertKept($theirs);
});

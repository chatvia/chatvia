<?php

use App\Identity\Chatter\Actions\CreateChatterAction;
use App\Identity\Chatter\Data\CreateChatterData;
use App\Identity\Chatter\Models\Chatter;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Data\Memory\ChatViewerData;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Services\Memory\ChatCache;
use Illuminate\Support\Str;
use Tests\Support\Conversation\HostedChatVisitor;

/**
 * The regenerate endpoint takes the conversation id in the body, so its form
 * request checks the conversation itself: a missing, deleted or other agent's
 * conversation is a 404 ("gone"), so a stale tab reports the honest status,
 * while a private agent's conversation the chatter does not own is a 403.
 * A question whose answer never landed can be taken back on its own.
 */
function mrcPayload(string $conversationId): array
{
    return [
        'conversation_id' => $conversationId,
        'user_message_id' => (string) Str::uuid(),
        'assistant_message_id' => (string) Str::uuid(),
    ];
}

it('answers 404 for a conversation that is gone or not the agent\'s', function (Closure $conversationOf): void {
    $agent = Agent::factory()->create(['is_public' => true]);

    $this->postJson("/chat/{$agent->external_id}/messages/regenerate", mrcPayload($conversationOf($agent)))
        ->assertNotFound();
})->with([
    'soft-deleted' => [function (Agent $agent): string {
        $conversation = Conversation::factory()->forAgent($agent)->hostedChat()->create();
        $conversation->forceFill(['deleted_at' => now()])->saveQuietly();

        return $conversation->external_id;
    }],
    'never existed' => [fn (): string => (string) Str::uuid()],
    'of another agent' => [fn (): string => Conversation::factory()->forAgent(Agent::factory()->create(['is_public' => true]))->hostedChat()->create()->external_id],
]);

it('answers 403 on a private agent when the signed-in chatter does not own the conversation', function (): void {
    // The conversation exists and belongs to the agent, so it gets past the
    // 404 "gone" branch; only the ownership check refuses it.
    $agent = Agent::factory()->create(['is_public' => false]);
    $owner = Chatter::factory()->grantedTo($agent)->create();
    $intruder = app(CreateChatterAction::class)->execute(new CreateChatterData($agent->workspace, $agent, 'Intruder'))->refresh();
    $conversation = Conversation::factory()->forAgent($agent)->ofChatter($owner)->hostedChat()->create();
    ChatCache::rememberOwner($conversation->external_id, ChatViewerData::chatter($owner->id), $agent->workspaceExternalId());

    $this->actingAs($intruder, 'chat')
        ->postJson("/chat/{$agent->external_id}/messages/regenerate", mrcPayload($conversation->external_id))
        ->assertForbidden();
});

it('takes back a question whose answer never landed when no answer id is sent', function (): void {
    $agent = Agent::factory()->create(['is_public' => true]);
    $id = HostedChatVisitor::startConversation($agent, 'Draw a scatter plot');
    $memory = HostedChatVisitor::memory($agent, $id);

    $this->postJson("/chat/{$agent->slug}/messages/regenerate", [
        'conversation_id' => $id,
        'user_message_id' => $memory->getMessagesForConversation()[0]->id,
    ])
        ->assertOk()
        ->assertJsonPath('user_message_content', 'Draw a scatter plot');

    expect($memory->getMessagesForConversation())->toBe([]);
});

<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Models\Conversation;
use Illuminate\Support\Str;
use Tests\Support\Conversation\HostedChatVisitor;

/**
 * The regenerate endpoint takes the conversation id in the body, so its form
 * request checks the conversation itself: a missing, deleted or other agent's
 * conversation is a 404 ("gone"), so a stale tab reports the honest status.
 * A question whose answer never landed can be taken back on its own.
 */
function mrcPayload(string $conversationId): array
{
    return [
        'conversation_id' => $conversationId,
        'user_message_id' => (string) Str::uuid7(),
        'assistant_message_id' => (string) Str::uuid7(),
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
    'never existed' => [fn (): string => (string) Str::uuid7()],
    'of another agent' => [fn (): string => Conversation::factory()->forAgent(Agent::factory()->create(['is_public' => true]))->hostedChat()->create()->external_id],
]);

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

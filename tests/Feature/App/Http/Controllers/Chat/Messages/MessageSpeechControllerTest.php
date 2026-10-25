<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Message;
use Domain\Conversation\Services\Speech\MessageSpeechFiles;
use Domain\Llm\Contracts\Speaker;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\Conversation\HostedChatVisitor;
use Tests\Support\Llm\FakeSpeaker;

/**
 * The hosted-chat speaker button: the visitor hears a reply of their own
 * conversation as audio while the agent reads aloud; another visitor's
 * reply, or an agent that does not read aloud, is a 404.
 */
beforeEach(function (): void {
    Storage::fake(MessageSpeechFiles::DISK);
    app()->instance(Speaker::class, $this->speaker = new FakeSpeaker);
    $this->agent = Agent::factory()->create(['is_public' => true]);
    $this->agent->setSetting('allow_read_aloud', true, 'appearance');
});

function mscReply(Agent $agent): array
{
    $conversationId = HostedChatVisitor::startConversation($agent);
    $reply = Message::factory()->fromAssistant()->create([
        'chat_conversation_id' => Conversation::query()->firstWhere('external_id', $conversationId)->id,
        'content' => 'We open at nine.',
    ]);

    return [$conversationId, $reply->external_id];
}

function mscUrl(Agent $agent, string $conversationId, string $messageId): string
{
    return "/chat/{$agent->slug}/conversations/{$conversationId}/messages/{$messageId}/speech";
}

it('plays the visitor\'s reply aloud, and reads it only once', function (): void {
    [$conversationId, $messageId] = mscReply($this->agent);

    $this->get(mscUrl($this->agent, $conversationId, $messageId))
        ->assertOk()
        ->assertHeader('Content-Type', 'audio/mpeg');
    $this->get(mscUrl($this->agent, $conversationId, $messageId))->assertOk();

    expect($this->speaker->spoken)->toHaveCount(1);
});

it('is a 404 for another visitor\'s reply', function (): void {
    [$conversationId, $messageId] = mscReply($this->agent);

    $this->flushSession();

    $this->get(mscUrl($this->agent, $conversationId, $messageId))->assertNotFound();
    expect($this->speaker->spoken)->toBe([]);
});

it('is a 404 while the agent does not read aloud', function (): void {
    [$conversationId, $messageId] = mscReply($this->agent);
    $this->agent->setSetting('allow_read_aloud', false, 'appearance');

    $this->get(mscUrl($this->agent, $conversationId, $messageId))->assertNotFound();
});

it('answers with a message when the speech service fails', function (): void {
    app()->instance(Speaker::class, new FakeSpeaker(failure: new RuntimeException('provider down')));
    [$conversationId, $messageId] = mscReply($this->agent);

    $this->getJson(mscUrl($this->agent, $conversationId, $messageId))
        ->assertStatus(503)
        ->assertJsonStructure(['message']);
});

it('only takes uuids', function (): void {
    $this->get("/chat/{$this->agent->slug}/conversations/".Str::uuid().'/messages/not-a-uuid/speech')->assertNotFound();
});

it('tells the chat page to show the speaker button', function (): void {
    $this->get("/chat/{$this->agent->slug}")
        ->assertInertia(fn ($page) => $page->where('readAloud', true));
});

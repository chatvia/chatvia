<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Services\Voice\VoiceCallStore;
use Domain\Llm\Enums\AiProvider;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\Support\Conversation\Voice\VoiceCalls;

/**
 * A hosted-chat visitor presses End on their own call; any other call is not
 * found. How the call is hung up is the end-call action's.
 */
// Voice conversations need a license key; the free-use limit itself is
// covered in VoiceAvailabilityTest.
beforeEach(fn () => withLicense());

it('hangs up the visitor\'s own call and 404s another one', function (): void {
    connectAiProviders(AiProvider::OpenAi);
    config(['chatvia.ai.voice' => 'auto', 'horizon.defaults.voice.maxProcesses' => 1]);
    Queue::fake();
    VoiceCalls::fakeOpenAi();
    $agent = Agent::factory()->create(['is_public' => true]);
    $agent->setSetting('allow_voice_conversation', true, 'appearance');
    $chat = '/chat/'.$agent->slug;
    $callId = $this->postJson($chat.'/voice', ['sdp' => 'v=0 offer'])->json('call_id');

    $this->deleteJson($chat.'/voice/calls/'.$callId)->assertNoContent();
    $this->deleteJson($chat.'/voice/calls/'.Str::uuid7())->assertNotFound();

    expect(app(VoiceCallStore::class)->isEnded($callId))->toBeTrue();
});

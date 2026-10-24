<?php

use Domain\Agent\Models\Agent;
use Illuminate\Http\UploadedFile;

/**
 * Dictation in the hosted chat composer: while speech to text is not set up
 * the endpoint answers with a message the composer can show.
 */
it('answers dictation with a message the composer can show when speech to text is off', function (): void {
    config(['chatvia.ai.transcriber' => 'none']);
    $agent = Agent::factory()->create(['is_public' => true]);

    $this->post("/chat/{$agent->slug}/transcribe", ['audio' => UploadedFile::fake()->create('clip.webm', 10, 'audio/webm')], ['Accept' => 'application/json'])
        ->assertStatus(503)
        ->assertJsonPath('message', 'Voice input is not available right now.');
});

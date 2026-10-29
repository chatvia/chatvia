<?php

use App\Identity\Chatter\Models\Chatter;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Models\Conversation;
use Tests\Support\Api\ActsAsApiOwner;

/**
 * Opening an agent's conversation through the public API: on the `api`
 * surface, hidden for a day, optionally for one of the workspace's chatters
 * who may use the agent. Any other chatter is a field error.
 */
uses(ActsAsApiOwner::class);

beforeEach(function (): void {
    $this->actAsApiOwner();
    $this->uri = "/api/v1/agents/{$this->agent->external_id}/conversations";
});

it('opens a conversation on the api surface for the chatter it names, hidden for a day', function (): void {
    $this->freezeSecond();
    $chatter = Chatter::factory()->grantedTo($this->agent)->create();

    $id = $this->withToken($this->token)
        ->postJson($this->uri, ['title' => 'Support run', 'chatter_id' => $chatter->external_id])
        ->assertCreated()
        ->json('data.id');

    expect(Conversation::query()->where('external_id', $id)->sole())
        ->agent_id->toBe($this->agent->id)
        ->source->toBe(ConversationSource::Api)
        ->title->toBe('Support run')
        ->session->chatter_id->toBe($chatter->id)
        ->expires_at->equalTo(now()->addDay())->toBeTrue();
});

it('rejects a chatter who may not use the agent as a field error and opens nothing', function (Closure $chatter): void {
    $this->withToken($this->token)
        ->postJson($this->uri, ['chatter_id' => $chatter($this->owner)->external_id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['chatter_id']);

    expect(Conversation::query()->where('agent_id', $this->agent->id)->exists())->toBeFalse();
})->with([
    'a chatter of the workspace without access' => [fn ($owner): Chatter => Chatter::factory()->ofWorkspace($owner)->create()],
    "another workspace's chatter" => [fn (): Chatter => Chatter::factory()->create()],
]);

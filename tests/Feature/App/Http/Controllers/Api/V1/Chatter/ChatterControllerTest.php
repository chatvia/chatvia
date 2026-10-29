<?php

use App\Identity\Chatter\Models\Chatter;
use Tests\Support\Api\ActsAsApiOwner;

/**
 * Changing a chatter's access controls through the public API: the monthly
 * message cap and the deactivation switch. A field left out keeps its value. The list is tested with every other list
 * in ApiListPaginationTest.
 */
uses(ActsAsApiOwner::class);

beforeEach(function (): void {
    $this->actAsApiOwner();
    $this->chatter = Chatter::factory()->ofWorkspace($this->owner)->create();
    $this->uri = "/api/v1/chatters/{$this->chatter->external_id}";
});

it('sets the monthly cap and deactivates the chatter with a reason', function (): void {
    $this->withToken($this->token)
        ->patchJson($this->uri, ['messages_limit' => 50, 'deactivated' => true, 'deactivation_reason' => 'Trial over'])
        ->assertOk()
        ->assertJsonPath('data.messages_limit', 50)
        ->assertJsonPath('data.deactivated', true);

    expect($this->chatter->fresh()->deactivation_reason)->toBe('Trial over');
});

it('clears the cap with null and keeps a field it was not sent', function (): void {
    $this->withToken($this->token)->patchJson($this->uri, ['messages_limit' => 50, 'deactivated' => true])->assertOk();

    $this->withToken($this->token)
        ->patchJson($this->uri, ['messages_limit' => null])
        ->assertOk()
        ->assertJsonPath('data.messages_limit', null)
        ->assertJsonPath('data.deactivated', true);

    $this->withToken($this->token)
        ->patchJson($this->uri, ['deactivated' => false])
        ->assertOk()
        ->assertJsonPath('data.deactivated', false)
        ->assertJsonPath('data.messages_limit', null);
});

<?php

use App\Identity\Chatter\Models\Chatter;
use Tests\Support\Api\ActsAsApiOwner;

/**
 * Revoking agents from a chatter through the public API: only the named
 * agents go and the chatter stays.
 */
uses(ActsAsApiOwner::class);

it('revokes the named agents and keeps the rest', function (): void {
    $this->actAsApiOwner();
    $second = createAgent($this->owner);
    $chatter = Chatter::factory()->grantedTo($this->agent)->create();
    $chatter->agents()->attach($second->id, ['workspace_id' => $second->workspace_id]);

    $this->withToken($this->token)
        ->deleteJson("/api/v1/chatters/{$chatter->external_id}/agents", ['agent_ids' => [$this->agent->external_id]])
        ->assertNoContent();

    expect($chatter->agents()->pluck('agents.id')->all())->toEqual([$second->id]);
});

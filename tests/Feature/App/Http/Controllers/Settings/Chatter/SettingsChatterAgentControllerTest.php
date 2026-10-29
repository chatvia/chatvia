<?php

use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Models\User;

/**
 * The Agents tab of a chatter's detail sheet (SettingsChatterAgentController):
 * granting and revoking a chatter's access to an agent goes through
 * `can.access.agent`, so a teammate can only grant agents their team role
 * reaches, and the list offers only those.
 */
beforeEach(function (): void {
    $this->owner = User::factory()->create();
    $this->support = createAgent($this->owner, ['name' => 'Support Bot']);
    $this->billing = createAgent($this->owner, ['name' => 'Billing Bot']);
    $this->chatter = Chatter::factory()->ofWorkspace($this->owner)->create();
});

it('lets the owner grant a chatter any protected agent of the workspace', function (): void {
    $this->actingAs($this->owner)
        ->postJson(route('people.chatters.agents.attach', ['chatter' => $this->chatter->external_id, 'agent' => $this->billing->external_id]))
        ->assertSuccessful()
        ->assertJsonPath('attached.0.id', $this->billing->external_id);

    expect($this->chatter->agents()->whereKey($this->billing->id)->exists())->toBeTrue();
});

it('refuses a teammate a grant on an agent their team role does not reach', function (): void {
    $teammate = teammateWithPermissions($this->owner, ['account.chatters.manage'], allAgents: false, agentExternalIds: [$this->support->external_id]);

    $this->actingAs($teammate)
        ->postJson(route('people.chatters.agents.attach', ['chatter' => $this->chatter->external_id, 'agent' => $this->billing->external_id]))
        ->assertForbidden();

    expect($this->chatter->agents()->whereKey($this->billing->id)->exists())->toBeFalse();
});

it('offers a teammate only the agents their team role reaches', function (): void {
    $teammate = teammateWithPermissions($this->owner, ['account.chatters.manage'], allAgents: false, agentExternalIds: [$this->support->external_id]);

    $this->actingAs($teammate)
        ->getJson(route('people.chatters.agents.index', ['chatter' => $this->chatter->external_id]))
        ->assertOk()
        ->assertJsonCount(0, 'attached')
        ->assertJsonCount(1, 'available')
        ->assertJsonPath('available.0.id', $this->support->external_id);
});

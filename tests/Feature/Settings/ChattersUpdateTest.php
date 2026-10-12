<?php

/**
 * Workspace Members page: a chatter's access controls (PATCH
 * people.chatters.update): the monthly message cap on the chatter's own
 * `message_limits.chatter_id` row and the deactivation switch. Only chatters
 * of the signed-in workspace resolve.
 */

use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Models\MessageLimit;
use App\Identity\Workspace\Models\User;
use Domain\Membership\Team\Services\TeamService;

beforeEach(function (): void {
    $this->owner = User::factory()->create();
    app(TeamService::class)->createDefaultTeam($this->owner);
    $this->chatter = Chatter::factory()->ofWorkspace($this->owner)->create(['name' => 'Casey']);
});

it('sets a chatter monthly message limit and returns the refreshed row', function (): void {
    $this->actingAs($this->owner)
        ->patchJson(route('people.chatters.update', ['chatter' => $this->chatter->external_id]), [
            'messages_limit' => 500,
            'deactivated' => false,
        ])
        ->assertOk()
        ->assertJsonPath('messages_limit', 500)
        ->assertJsonPath('messages_used', 0)
        ->assertJsonPath('is_deactivated', false);

    expect(MessageLimit::query()->where('chatter_id', $this->chatter->id)->value('monthly_limit'))->toBe(500)
        // The workspace's own limit is a different row and stays unset.
        ->and(MessageLimit::query()->where('workspace_id', $this->owner->workspace()->id)->exists())->toBeFalse();
});

it('deactivates a chatter with a reason', function (): void {
    $this->actingAs($this->owner)
        ->patchJson(route('people.chatters.update', ['chatter' => $this->chatter->external_id]), [
            'messages_limit' => null,
            'deactivated' => true,
            'deactivation_reason' => 'Abuse',
        ])
        ->assertOk()
        ->assertJsonPath('is_deactivated', true)
        ->assertJsonPath('deactivation_reason', 'Abuse');

    expect($this->chatter->fresh()->isDeactivated())->toBeTrue()
        ->and($this->owner->fresh()->isDeactivated())->toBeFalse();
});

it('clears the limit when messages_limit is null', function (): void {
    $owner = $this->owner;
    $chatter = $this->chatter;

    $this->actingAs($owner)->patchJson(route('people.chatters.update', ['chatter' => $chatter->external_id]), [
        'messages_limit' => 100,
        'deactivated' => false,
    ])->assertOk();

    $this->actingAs($owner)->patchJson(route('people.chatters.update', ['chatter' => $chatter->external_id]), [
        'messages_limit' => null,
        'deactivated' => false,
    ])->assertOk()->assertJsonPath('messages_limit', null);

    expect(MessageLimit::query()->where('chatter_id', $chatter->id)->exists())->toBeFalse();
});

it('404s for a chatter outside the workspace', function (): void {
    $otherOwner = User::factory()->create();
    app(TeamService::class)->createDefaultTeam($otherOwner);
    $foreign = Chatter::factory()->ofWorkspace($otherOwner)->create();

    $this->actingAs($this->owner)
        ->patchJson(route('people.chatters.update', ['chatter' => $foreign->external_id]), [
            'messages_limit' => 10,
            'deactivated' => false,
        ])
        ->assertNotFound();

    expect(MessageLimit::query()->where('chatter_id', $foreign->id)->exists())->toBeFalse();
});

it('404s for a teammate external id: only chatters resolve', function (): void {
    $teammate = User::factory()->employee($this->owner)->create();

    $this->actingAs($this->owner)
        ->patchJson(route('people.chatters.update', ['chatter' => $teammate->external_id]), [
            'messages_limit' => null,
            'deactivated' => true,
        ])
        ->assertNotFound();

    expect($teammate->fresh()->isDeactivated())->toBeFalse();
});

it('validates the message limit is a positive integer', function (): void {
    $this->actingAs($this->owner)
        ->patchJson(route('people.chatters.update', ['chatter' => $this->chatter->external_id]), [
            'messages_limit' => 0,
            'deactivated' => false,
        ])
        ->assertJsonValidationErrors('messages_limit');
});

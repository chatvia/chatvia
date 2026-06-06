<?php

use App\Identity\Workspace\Models\User;
use Domain\Billing\Models\UserCredit;
use Domain\Membership\Team\Services\TeamService;

beforeEach(function (): void {
    $this->owner = User::factory()->create();
    app(TeamService::class)->createDefaultTeam($this->owner);
    $this->chatter = User::factory()->chatter($this->owner)->create(['name' => 'Casey']);
});

it('sets a chatter monthly message limit and returns the refreshed row', function (): void {
    $this->actingAs($this->owner)
        ->patchJson(route('settings.members.update', ['chatter' => $this->chatter->external_id]), [
            'messages_limit' => 500,
            'deactivated' => false,
        ])
        ->assertOk()
        ->assertJsonPath('messages_limit', 500)
        ->assertJsonPath('messages_used', 0)
        ->assertJsonPath('is_deactivated', false);

    expect(UserCredit::query()->where('user_id', $this->chatter->id)->value('messages'))->toBe(500);
});

it('deactivates a chatter with a reason', function (): void {
    $this->actingAs($this->owner)
        ->patchJson(route('settings.members.update', ['chatter' => $this->chatter->external_id]), [
            'messages_limit' => null,
            'deactivated' => true,
            'deactivation_reason' => 'Abuse',
        ])
        ->assertOk()
        ->assertJsonPath('is_deactivated', true)
        ->assertJsonPath('deactivation_reason', 'Abuse');

    expect($this->chatter->fresh()->isDeactivated())->toBeTrue();
});

it('clears the limit when messages_limit is null', function (): void {
    $owner = $this->owner;
    $chatter = $this->chatter;

    $this->actingAs($owner)->patchJson(route('settings.members.update', ['chatter' => $chatter->external_id]), [
        'messages_limit' => 100,
        'deactivated' => false,
    ])->assertOk();

    $this->actingAs($owner)->patchJson(route('settings.members.update', ['chatter' => $chatter->external_id]), [
        'messages_limit' => null,
        'deactivated' => false,
    ])->assertOk()->assertJsonPath('messages_limit', null);

    expect(UserCredit::query()->where('user_id', $chatter->id)->exists())->toBeFalse();
});

it('404s for a chatter outside the workspace', function (): void {
    $otherOwner = User::factory()->create();
    app(TeamService::class)->createDefaultTeam($otherOwner);
    $foreign = User::factory()->chatter($otherOwner)->create();

    $this->actingAs($this->owner)
        ->patchJson(route('settings.members.update', ['chatter' => $foreign->external_id]), [
            'messages_limit' => 10,
            'deactivated' => false,
        ])
        ->assertNotFound();
});

it('validates the message limit is a positive integer', function (): void {
    $this->actingAs($this->owner)
        ->patchJson(route('settings.members.update', ['chatter' => $this->chatter->external_id]), [
            'messages_limit' => 0,
            'deactivated' => false,
        ])
        ->assertJsonValidationErrors('messages_limit');
});

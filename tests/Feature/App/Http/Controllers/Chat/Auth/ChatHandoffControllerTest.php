<?php

use App\Identity\Workspace\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * The staff-only hand-off from the dashboard into an agent's hosted chat: a
 * staff member who may use the agent gets a one-time `auth_token` link and
 * keeps their dashboard session; anyone else is refused.
 */
beforeEach(function (): void {
    $this->owner = User::factory()->create();
    $this->agent = createAgent($this->owner, ['slug' => Str::lower(Str::random(12))]);
    $this->chat = '/chat/'.$this->agent->slug;
});

it('keeps a staff member\'s dashboard session at the hand-off', function (): void {
    $location = $this->actingAs($this->owner)
        ->get('/chat-handoff/'.$this->agent->slug)
        ->assertRedirect()
        ->headers->get('Location');

    expect($location)->toContain($this->chat.'?auth_token=')
        ->and(Auth::guard('web')->id())->toBe($this->owner->id);
});

it('refuses the hand-off to an agent the user cannot use', function (): void {
    $stranger = User::factory()->create();

    $this->actingAs($stranger)
        ->get('/chat-handoff/'.$this->agent->slug)
        ->assertForbidden();
});

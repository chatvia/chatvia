<?php

use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * A signed-in chatter changes their password from the hosted chat profile. The
 * current password is checked against the chat's own `chat` guard; the
 * dashboard's `web` guard has no user on /chat requests.
 */
beforeEach(function (): void {
    $this->owner = User::factory()->create();
    $this->agent = createAgent($this->owner, ['slug' => Str::lower(Str::random(12))]);
    $this->chatter = Chatter::factory()->grantedTo($this->agent)->create([
        'password' => Hash::make('old-password'),
    ]);
    $this->profile = "/chat/{$this->agent->slug}/profile";
});

it('changes the password when the current one is right', function (): void {
    $this->actingAs($this->chatter, 'chat')
        ->from("/chat/{$this->agent->slug}")
        ->patch($this->profile, [
            'current_password' => 'old-password',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])
        ->assertSessionHasNoErrors();

    expect(Hash::check('new-password-123', $this->chatter->fresh()->password))->toBeTrue();
});

it('keeps the password when the current one is wrong', function (): void {
    $this->actingAs($this->chatter, 'chat')
        ->from("/chat/{$this->agent->slug}")
        ->patch($this->profile, [
            'current_password' => 'not-it',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])
        ->assertSessionHasErrors('current_password');

    expect(Hash::check('old-password', $this->chatter->fresh()->password))->toBeTrue();
});

it('refuses a staff account signed in to the chat', function (): void {
    $this->actingAs($this->owner, 'chat_staff')
        ->from("/chat/{$this->agent->slug}")
        ->patch($this->profile, ['name' => 'Someone else'])
        ->assertForbidden();
});

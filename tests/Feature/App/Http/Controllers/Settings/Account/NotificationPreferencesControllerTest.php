<?php

use App\Identity\Workspace\Models\User;
use Inertia\Testing\AssertableInertia;

/**
 * Settings → Account → Notifications: the person's bell, desktop and sound
 * switches per category, shown and saved. Every switch of every category is
 * required and must be true or false.
 */
beforeEach(function (): void {
    $this->owner = User::factory()->create();
    createAgent($this->owner);
});

it("shows and saves the account's notification switches", function (): void {
    $this->actingAs($this->owner)->get('/my/account/notifications')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('account/Notifications')
            ->where('preferences.security.desktop', true));

    $switches = collect(['handoffs', 'security', 'training', 'leads', 'usage', 'reports'])
        ->mapWithKeys(fn (string $category): array => [$category => ['bell' => true, 'desktop' => false, 'sound' => false]])
        ->all();
    $switches['leads'] = ['bell' => false, 'desktop' => true, 'sound' => true];

    $this->from('/my/account/notifications')->patch('/my/account/notifications', $switches)
        ->assertRedirect('/my/account/notifications')
        ->assertSessionHasNoErrors();

    $preferences = $this->owner->fresh()->notificationPreferences();
    expect($preferences->leads->bell)->toBeFalse()
        ->and($preferences->leads->desktop)->toBeFalse()
        ->and($preferences->handoffs->sound)->toBeFalse();
});

it('refuses switches that are missing or not true or false', function (): void {
    $this->actingAs($this->owner)
        ->patch('/my/account/notifications', ['handoffs' => ['bell' => 'maybe']])
        ->assertSessionHasErrors(['handoffs.bell', 'handoffs.desktop', 'security']);
});

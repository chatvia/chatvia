<?php

use App\Identity\Workspace\Models\User;
use Domain\Notification\Enums\NotificationKind;
use Domain\Notification\Models\UserNotification;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    $this->owner = User::factory()->create();
    createAgent($this->owner);
});

it('shares the unread count and the alert switches with every dashboard page', function (): void {
    UserNotification::factory()->for($this->owner)->count(2)->create();
    UserNotification::factory()->for($this->owner)->read()->create();

    $this->actingAs($this->owner)->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('notifications.unread', 2)
            ->where('notifications.preferences.handoffs.sound', true));
});

it('renders the Notifications page with the newest activity first', function (): void {
    $older = UserNotification::factory()->for($this->owner)->create(['updated_at' => now()->subHour()]);
    $newer = UserNotification::factory()->for($this->owner)->ofKind(NotificationKind::LeadCaptured)->create();

    $this->actingAs($this->owner)->get('/my/notifications')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('notifications/Index')
            ->where('filter', 'all')
            ->where('feed.unread', 2)
            ->has('feed.notifications', 2)
            ->where('feed.notifications.0.id', $newer->external_id)
            ->where('feed.notifications.0.kind', 'lead_captured')
            ->where('feed.notifications.0.category', 'leads')
            ->where('feed.notifications.1.id', $older->external_id));
});

it('pages the feed by cursor and filters unread ones', function (): void {
    UserNotification::factory()->for($this->owner)->count(22)->sequence(fn ($sequence): array => ['updated_at' => now()->subMinutes($sequence->index)])->create();
    $read = UserNotification::factory()->for($this->owner)->read()->create(['updated_at' => now()->addMinute()]);

    $first = $this->actingAs($this->owner)->getJson('/my/notifications/feed')->assertOk();
    expect($first->json('notifications'))->toHaveCount(20)
        ->and($first->json('notifications.0.id'))->toBe($read->external_id)
        ->and($first->json('unread'))->toBe(22)
        ->and($first->json('next_cursor'))->not->toBeNull();

    $second = $this->getJson('/my/notifications/feed?cursor='.$first->json('next_cursor'))->assertOk();
    expect($second->json('notifications'))->toHaveCount(3)
        ->and($second->json('next_cursor'))->toBeNull();

    $unread = $this->getJson('/my/notifications/feed?filter=unread')->assertOk();
    expect(collect($unread->json('notifications'))->pluck('read')->unique()->all())->toBe([false]);
});

it('never lists someone else\'s notifications', function (): void {
    UserNotification::factory()->create();

    $this->actingAs($this->owner)->getJson('/my/notifications/feed')
        ->assertOk()
        ->assertJsonCount(0, 'notifications')
        ->assertJsonPath('unread', 0);
});

it('marks one notification read', function (): void {
    $notification = UserNotification::factory()->for($this->owner)->create();

    $this->actingAs($this->owner)->patchJson('/my/notifications/'.$notification->external_id)->assertNoContent();

    expect($notification->fresh()->read_at)->not->toBeNull();
});

it('answers 404 for someone else\'s notification and leaves it unread', function (): void {
    $theirs = UserNotification::factory()->create();

    $this->actingAs($this->owner)->patchJson('/my/notifications/'.$theirs->external_id)->assertNotFound();

    expect($theirs->fresh()->read_at)->toBeNull();
});

it('marks all of the person\'s notifications read', function (): void {
    UserNotification::factory()->for($this->owner)->count(3)->create();
    $theirs = UserNotification::factory()->create();

    $this->actingAs($this->owner)->postJson('/my/notifications/read-all')->assertNoContent();

    expect($this->owner->userNotifications()->unread()->count())->toBe(0)
        ->and($theirs->fresh()->read_at)->toBeNull();
});

it('clears all of the person\'s notifications and nobody else\'s', function (): void {
    UserNotification::factory()->for($this->owner)->count(2)->create();
    $theirs = UserNotification::factory()->create();

    $this->actingAs($this->owner)->deleteJson('/my/notifications')->assertNoContent();

    expect($this->owner->userNotifications()->count())->toBe(0)
        ->and($theirs->fresh())->not->toBeNull();
});

it('shows and saves the account\'s notification switches', function (): void {
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

<?php

use App\Identity\Workspace\Models\User;
use Domain\Notification\Models\UserNotification;
use Illuminate\Database\Eloquent\Factories\Sequence;

/**
 * The notification feed the bell loads: the person's own notifications,
 * newest activity first, twenty a page with a cursor, all or only the
 * unread ones.
 */
beforeEach(function (): void {
    $this->owner = User::factory()->create();
    createAgent($this->owner);
});

it('pages the feed by cursor and filters the unread ones', function (): void {
    UserNotification::factory()->for($this->owner)->count(22)->sequence(fn (Sequence $sequence): array => ['updated_at' => now()->subMinutes($sequence->index)])->create();
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

it("never lists someone else's notifications", function (): void {
    UserNotification::factory()->create();

    $this->actingAs($this->owner)->getJson('/my/notifications/feed')
        ->assertOk()
        ->assertJsonCount(0, 'notifications')
        ->assertJsonPath('unread', 0);
});

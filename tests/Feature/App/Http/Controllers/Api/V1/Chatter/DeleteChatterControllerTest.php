<?php

use App\Identity\Chatter\Models\Chatter;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Api\ActsAsApiOwner;

/**
 * Deleting a chatter through the public API removes the chatter. What else
 * goes with it (grants, chats, files) is DeleteChatterAction's and is tested
 * there.
 */
uses(ActsAsApiOwner::class);

beforeEach(function (): void {
    // Deleting a chatter wipes its photo on the assets disk and its
    // attachment files on the private disk.
    Storage::fake('private');
    Storage::fake('assets');
    $this->actAsApiOwner();
});

it('deletes a chatter of the workspace', function (): void {
    $chatter = Chatter::factory()->grantedTo($this->agent)->create();

    $this->withToken($this->token)
        ->deleteJson("/api/v1/chatters/{$chatter->external_id}")
        ->assertNoContent();

    expect(Chatter::whereKey($chatter->id)->exists())->toBeFalse();
});

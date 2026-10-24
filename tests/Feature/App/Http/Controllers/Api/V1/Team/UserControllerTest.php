<?php

use Tests\Support\Api\ActsAsApiOwner;

/**
 * The public API's list of the workspace's staff accounts, there to pick a
 * `user_id` for a role or a sign-in link. Paging is tested in
 * ApiListPaginationTest.
 */
uses(ActsAsApiOwner::class);

beforeEach(function (): void {
    $this->actAsApiOwner();
});

it('lists the workspace users with their email', function (): void {
    $this->withToken($this->token)
        ->getJson('/api/v1/users')
        ->assertOk()
        ->assertJsonPath('data.0.id', $this->owner->external_id)
        ->assertJsonPath('data.0.email', $this->owner->email);
});

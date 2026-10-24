<?php

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;

/**
 * Renaming an agent refuses a name or a chat address another agent of the
 * workspace already uses.
 */
it('refuses a name or an address another agent of the workspace uses', function (string $field, Closure $body): void {
    $owner = User::factory()->create();
    $agent = createAgent($owner, ['name' => 'Support Bot']);
    $other = createAgent($owner, ['name' => 'Sales Bot', 'slug' => 'sales-bot']);

    $this->actingAs($owner)
        ->patchJson(route('agents.rename', $agent), $body($other))
        ->assertJsonValidationErrors([$field]);

    expect($agent->fresh()->name)->toBe('Support Bot');
})->with([
    'name' => ['name', fn (Agent $other): array => ['name' => $other->name, 'slug' => 'support-bot']],
    'address' => ['slug', fn (Agent $other): array => ['name' => 'Support Two', 'slug' => $other->slug]],
]);

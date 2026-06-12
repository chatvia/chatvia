<?php

use App\Identity\Workspace\Models\User;
use Inertia\Testing\AssertableInertia;

it('shows the managing workspace name for an employee', function (): void {
    $workspace = User::factory()->workspace()->create(['name' => 'Acme Corp']);
    $employee = User::factory()->employee($workspace)->create();

    $this->actingAs($employee)
        ->get(route('account.profile.edit'))
        ->assertOk()
        ->assertInertia(
            fn (AssertableInertia $page): AssertableInertia => $page
                ->component('account/Profile')
                ->where('managedBy', 'Acme Corp'),
        );
});

it('does not show a managing workspace for a standalone owner', function (): void {
    $owner = User::factory()->workspace()->create();

    $this->actingAs($owner)
        ->get(route('account.profile.edit'))
        ->assertOk()
        ->assertInertia(
            fn (AssertableInertia $page): AssertableInertia => $page
                ->component('account/Profile')
                ->where('managedBy', null),
        );
});

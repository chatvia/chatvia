<?php

use App\Identity\Workspace\Enums\DateFormat;
use App\Identity\Workspace\Models\User;
use Inertia\Testing\AssertableInertia;

/**
 * The Profile page (ProfileController@edit): the date format choices, and for
 * a teammate the name of the workspace that manages the account.
 */
it('shows the profile page with the date formats and the managing workspace', function (bool $teammate): void {
    $owner = User::factory()->create(['name' => 'Acme Corp']);
    $viewer = $teammate ? User::factory()->employee($owner)->create() : $owner;

    $this->actingAs($viewer)
        ->get(route('account.profile.edit'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('account/Profile')
            ->has('dateFormats', count(DateFormat::cases()))
            ->where('managedBy', $teammate ? 'Acme Corp' : null));
})->with([
    'a teammate sees the workspace managing it' => true,
    'an owner manages itself' => false,
]);

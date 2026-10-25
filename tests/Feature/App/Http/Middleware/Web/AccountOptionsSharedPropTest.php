<?php

use App\Identity\Workspace\Models\User;
use Inertia\Testing\AssertableInertia;

/**
 * HandleInertiaRequests shares whether owners may download their data and
 * delete their account (Settings → Workspaces → Owner account), so the
 * Account menu hides an entry that is switched off.
 */
it('shares the owner account switches with the dashboard', function (bool $dataExport, bool $deletion): void {
    config(['chatvia.account.data_export' => $dataExport, 'chatvia.account.deletion' => $deletion]);
    $owner = User::factory()->create();

    $this->actingAs($owner)->get(route('account.profile.edit'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('accountOptions.data_export', $dataExport)
            ->where('accountOptions.deletion', $deletion));
})->with([
    'both on' => [true, true],
    'both off' => [false, false],
    'only the download' => [true, false],
]);

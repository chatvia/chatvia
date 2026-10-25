<?php

use App\Identity\Workspace\Models\User;
use Illuminate\Support\Facades\Auth;
use Inertia\Testing\AssertableInertia;

/*
 * The chat log offers a "trace" link into Spectra only to a viewer who may
 * actually open /spectra: an active admin signed in to the admin panel's own
 * guard, or the owner of a workspace an admin granted Spectra access.
 * Spectra shows every workspace's AI calls, so everyone else gets null,
 * which hides the link.
 */
beforeEach(function (): void {
    config(['spectra.enabled' => true]);

    $this->owner = User::factory()->create();
    $this->agent = createAgent($this->owner);
});

it('hides the Spectra trace link from a dashboard-only session', function (): void {
    $this->actingAs($this->owner)
        ->get(route('agents.logs', $this->agent))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->where('spectraTraceUrl', null));
});

it('offers the Spectra trace link to an admin signed in to the admin panel', function (): void {
    // Both sessions at once, as in a real browser: the dashboard on the web
    // guard, the admin panel on its own. setUser keeps `web` the default guard.
    $this->actingAs($this->owner);
    Auth::guard('admin')->setUser(adminUser());

    $this->get(route('agents.logs', $this->agent))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->where('spectraTraceUrl', url('spectra/requests')));
});

it('offers the Spectra trace link to the owner of a workspace granted Spectra access', function (): void {
    $this->owner->workspace()->update(['spectra_access' => true]);

    $this->actingAs($this->owner)
        ->get(route('agents.logs', $this->agent))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->where('spectraTraceUrl', url('spectra/requests')));
});

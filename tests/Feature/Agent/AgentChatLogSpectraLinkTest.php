<?php

use App\Identity\Workspace\Models\User;
use Domain\Membership\Team\Services\TeamService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia;

/*
 * The chat log offers a "trace" link into Spectra only to a viewer who may
 * actually open /spectra: an active admin signed in to the admin panel's own
 * guard, or the staff of a workspace an admin granted Spectra access.
 * Spectra shows every workspace's AI calls, so everyone else gets null,
 * which hides the link.
 */

beforeEach(function (): void {
    $this->owner = User::factory()->create();
    app(TeamService::class)->createDefaultTeam($this->owner);
    $this->owner->refresh();

    $this->agent = createAgent($this->owner);
});

it('hides the Spectra trace link from a dashboard-only session', function (): void {
    $this->actingAs($this->owner)
        ->get(route('agents.logs', $this->agent))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->where('spectraTraceUrl', null));
});

it('offers the Spectra trace link to an admin signed in to the admin panel', function (): void {
    config(['spectra.enabled' => true]);

    // Both sessions at once, as in a real browser: the dashboard on the web
    // guard, the admin panel on its own. setUser keeps `web` the default guard.
    $this->actingAs($this->owner);
    Auth::guard('admin')->setUser(User::factory()->admin()->create()->fresh());

    $this->get(route('agents.logs', $this->agent))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->where('spectraTraceUrl', url('spectra/requests')));
});

it('offers the Spectra trace link to the staff of a workspace granted Spectra access', function (): void {
    config(['spectra.enabled' => true]);
    $this->owner->workspace()->update(['spectra_access' => true]);

    $this->actingAs($this->owner)
        ->get(route('agents.logs', $this->agent))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->where('spectraTraceUrl', url('spectra/requests')));
});

it('opens Spectra itself only for a granted workspace', function (): void {
    $this->actingAs($this->owner);
    expect(Gate::allows('viewSpectra'))->toBeFalse();

    $this->owner->workspace()->update(['spectra_access' => true]);
    $this->owner->refresh();

    expect(Gate::allows('viewSpectra'))->toBeTrue();
});

<?php

use App\Identity\Workspace\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * The chat log offers a "trace" link into Spectra only to a viewer who may
 * actually open /spectra: an active admin signed in to the admin panel's own
 * guard, or the owner of a workspace an admin granted Spectra access.
 * Spectra shows every workspace's AI calls, so everyone else gets null.
 */
it('offers the Spectra trace link only to an admin or the owner of a workspace granted Spectra', function (): void {
    config(['spectra.enabled' => true]);
    $owner = User::factory()->create();
    $agent = createAgent($owner);
    $traceUrl = fn (): mixed => $this->actingAs($owner)->get(route('agents.logs', $agent))->assertOk()->inertiaProps('spectraTraceUrl');

    $dashboardOnly = $traceUrl();
    $owner->workspace()->update(['spectra_access' => true]);
    $granted = $traceUrl();
    $owner->workspace()->update(['spectra_access' => false]);
    Auth::guard('admin')->setUser(adminUser());
    $admin = $traceUrl();

    expect($dashboardOnly)->toBeNull()
        ->and($granted)->toBe(url('spectra/requests'))
        ->and($admin)->toBe(url('spectra/requests'));
});

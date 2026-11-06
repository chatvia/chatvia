<?php

use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Services\WorkspaceDashboards;
use Illuminate\Support\Facades\Auth;

/**
 * The chat log offers a "trace" link only to a viewer who may open the trace:
 * staff in Spectra, where every workspace's calls are, or the workspace's owner
 * on the workspace's own dashboard, when that shows traces.
 */
it('points the trace link at Spectra for staff, at the dashboard for the owner, and nowhere for anyone else', function (): void {
    config(['spectra.enabled' => true]);
    $owner = User::factory()->create();
    $agent = createAgent($owner);
    $teammate = teammateWithPermissions($owner, ['agent.chat.view']);
    $dashboards = app(WorkspaceDashboards::class);
    $traceUrl = fn (User $viewer): mixed => $this->actingAs($viewer)->get(route('agents.logs', $agent))->assertOk()->inertiaProps('spectraTraceUrl');

    $withoutDashboard = $traceUrl($owner);
    $dashboards->create($owner->workspace(), [], false);
    $withoutTraces = $traceUrl($owner);
    $dashboard = $dashboards->create($owner->workspace(), ['traces'], false);
    $withTraces = $traceUrl($owner);
    $forTeammate = $traceUrl($teammate);
    Auth::guard('admin')->setUser(adminUser());
    $forStaff = $traceUrl($owner);

    expect($withoutDashboard)->toBeNull()
        ->and($withoutTraces)->toBeNull()
        ->and($withTraces)->toBe($dashboard->url().'/traces/{trace}')
        ->and($forTeammate)->toBeNull()
        ->and($forStaff)->toBe(url('spectra/requests').'?trace_id={trace}');
});

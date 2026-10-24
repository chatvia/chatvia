<?php

use App\Admin\StaffTool;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/**
 * A staff tool of the Tools page says why it has no link: an install
 * without the tool's dashboard route does not have it.
 */
it('says a tool without its dashboard route is not installed', function (): void {
    Auth::guard('admin')->login(adminUser());

    expect(StaffTool::Pulse->unavailableReason())->toBeNull();

    Route::setRoutes(new RouteCollection);

    expect(StaffTool::Pulse->unavailableReason())->toBe('Not installed')
        ->and(StaffTool::Horizon->unavailableReason())->toBe('Not installed');
});

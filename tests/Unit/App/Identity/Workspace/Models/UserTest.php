<?php

use App\Identity\Workspace\Models\User;
use Filament\Facades\Filament;

/**
 * The staff account model: only an active platform admin gets into the
 * Filament admin panel (App\Admin\AdminPanelProvider), whatever their
 * dashboard access.
 */
it('lets only an active admin into the admin panel', function (bool $isAdmin, bool $deactivated, bool $allowed): void {
    $user = User::factory()->make(['is_admin' => $isAdmin, 'deactivated_at' => $deactivated ? now() : null]);

    expect($user->canAccessPanel(Filament::getPanel('admin')))->toBe($allowed);
})->with([
    'admin' => [true, false, true],
    'deactivated admin' => [true, true, false],
    'staff account' => [false, false, false],
]);

<?php

use App\Identity\Workspace\Models\User;
use Filament\Facades\Filament;

/**
 * The Filament admin panel (App\Admin\AdminPanelProvider) is gated to platform
 * staff only (`is_admin`), via User::canAccessPanel(). Owners and teammates
 * are turned away at the panel door regardless of dashboard access.
 */
it('only lets admins into the filament admin panel', function (bool $isAdmin, bool $allowed): void {
    $panel = Filament::getPanel('admin');
    $user = User::factory()->make(['is_admin' => $isAdmin]);

    expect($user->canAccessPanel($panel))->toBe($allowed);
})->with([
    'admin' => [true, true],
    'staff account' => [false, false],
]);

<?php

use App\Identity\Workspace\Models\User;
use Filament\Facades\Filament;
use Support\Enums\User\UserType;

/**
 * The Filament admin panel (App\Admin\AdminPanelProvider) is gated to internal
 * staff only, via User::canAccessPanel(). Every other account type is turned
 * away at the panel door regardless of dashboard access.
 */
it('only lets admins into the filament admin panel', function (UserType $type, bool $allowed): void {
    $panel = Filament::getPanel('admin');
    $user = User::factory()->make(['type' => $type]);

    expect($user->canAccessPanel($panel))->toBe($allowed);
})->with([
    'admin' => [UserType::Admin, true],
    'workspace owner' => [UserType::Workspace, false],
    'employee' => [UserType::Employee, false],
    'chatter' => [UserType::Chatter, false],
]);

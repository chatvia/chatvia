<?php

use App\Identity\Workspace\Models\User;
use Filament\Facades\Filament;

/**
 * The staff account model: its email is kept in lower case (a CHECK on
 * `users.email` refuses anything else), and only an active platform admin
 * gets into the Filament admin panel (App\Admin\AdminPanelProvider), whatever
 * their dashboard access.
 */
it('stores the email in lowercase', function (): void {
    $user = User::factory()->create(['email' => 'Mixed.Case@Example.TEST']);

    expect($user->fresh()->email)->toBe('mixed.case@example.test');
});

it('lets only an active admin into the admin panel', function (bool $isAdmin, bool $deactivated, bool $allowed): void {
    $user = User::factory()->make(['is_admin' => $isAdmin, 'deactivated_at' => $deactivated ? now() : null]);

    expect($user->canAccessPanel(Filament::getPanel('admin')))->toBe($allowed);
})->with([
    'admin' => [true, false, true],
    'deactivated admin' => [true, true, false],
    'staff account' => [false, false, false],
]);

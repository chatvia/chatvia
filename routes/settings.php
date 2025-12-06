<?php

use App\Http\Controllers\Settings\PasswordController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\TeamController;
use App\Http\Controllers\SettingsUsageController;
use App\Http\Middleware\DenyStaff;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware('auth')->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/password', [PasswordController::class, 'edit'])->name('password.edit');
    Route::put('settings/password', [PasswordController::class, 'update'])->name('password.update');

    Route::get('settings/usage', SettingsUsageController::class)->name('settings.usage');

    Route::get('settings/appearance', function () {
        return Inertia::render('settings/Appearance');
    })->name('appearance');

    // Team switching (allowed for staff)
    Route::post('settings/teams/{team}/switch', [TeamController::class, 'switch'])->name('teams.switch');

    // Teams management (denied for staff)
    Route::middleware(DenyStaff::class)->group(function () {
        Route::get('settings/teams', [TeamController::class, 'index'])->name('teams.index');
        Route::post('settings/teams', [TeamController::class, 'store'])->name('teams.store');
        Route::get('settings/teams/{team}', [TeamController::class, 'show'])->name('teams.show');
        Route::put('settings/teams/{team}', [TeamController::class, 'update'])->name('teams.update');
        Route::delete('settings/teams/{team}', [TeamController::class, 'destroy'])->name('teams.destroy');
        Route::post('settings/teams/{team}/leave', [TeamController::class, 'leave'])->name('teams.leave');

        // Team members
        Route::post('settings/teams/{team}/invite', [TeamController::class, 'inviteMember'])->name('teams.invite');
        Route::delete('settings/teams/{team}/invitations/{invitationId}', [TeamController::class, 'cancelInvitation'])->name('teams.cancel-invitation');
        Route::put('settings/teams/{team}/members/{member}', [TeamController::class, 'updateMemberRole'])->name('teams.update-member');
        Route::delete('settings/teams/{team}/members/{member}', [TeamController::class, 'removeMember'])->name('teams.remove-member');

        // Team roles
        Route::get('settings/teams/{team}/roles/create', [TeamController::class, 'showCreateRole'])->name('teams.roles.create');
        Route::post('settings/teams/{team}/roles', [TeamController::class, 'createRole'])->name('teams.create-role');
        Route::get('settings/teams/{team}/roles/{roleId}/edit', [TeamController::class, 'showEditRole'])->name('teams.roles.edit');
        Route::put('settings/teams/{team}/roles/{roleId}', [TeamController::class, 'updateRole'])->name('teams.update-role');
        Route::delete('settings/teams/{team}/roles/{roleId}', [TeamController::class, 'deleteRole'])->name('teams.delete-role');
    });
});

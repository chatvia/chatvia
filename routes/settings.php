<?php

use App\Http\Controllers\Settings\PasswordController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\SettingsUsageController;
use App\Http\Controllers\Teams\TeamController;
use App\Http\Controllers\Teams\TeamDeleteController;
use App\Http\Controllers\Teams\TeamLeaveController;
use App\Http\Controllers\Teams\TeamListController;
use App\Http\Controllers\Teams\TeamsHomeController;
use App\Http\Controllers\Teams\TeamShowController;
use App\Http\Controllers\Teams\TeamsStoreController;
use App\Http\Controllers\Teams\TeamUpdateController;
use App\Http\Middleware\DenyIfStaff;
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

    // Team switching and list (allowed for staff)
    Route::post('settings/teams/{team}/switch', [TeamController::class, 'switch'])->name('teams.switch');
    Route::get('my/teams/list', TeamListController::class)->name('my.teams.list');

    // Teams management (denied for staff)
    Route::middleware(DenyIfStaff::class)->group(function () {
        Route::get('my/teams', TeamsHomeController::class)->name('teams.index');
        Route::post('my/teams', TeamsStoreController::class)->name('teams.store');

        Route::get('my/teams/{team}', TeamShowController::class)->name('teams.show');
        Route::put('my/teams/{team}', TeamUpdateController::class)->name('teams.update');
        Route::delete('my/teams/{team}', TeamDeleteController::class)->name('teams.destroy');
        Route::post('my/teams/{team}/leave', TeamLeaveController::class)->name('teams.leave');

        // Team members
        Route::post('my/teams/{team}/invite', [TeamController::class, 'inviteMember'])->name('teams.invite');
        Route::delete('my/teams/{team}/invitations/{invitationId}', [TeamController::class, 'cancelInvitation'])->name('teams.cancel-invitation');
        Route::put('my/teams/{team}/members/{member}', [TeamController::class, 'updateMemberRole'])->name('teams.update-member');
        Route::delete('my/teams/{team}/members/{member}', [TeamController::class, 'removeMember'])->name('teams.remove-member');

        // Team roles
        Route::get('my/teams/{team}/roles/create', [TeamController::class, 'showCreateRole'])->name('teams.roles.create');
        Route::post('my/teams/{team}/roles', [TeamController::class, 'createRole'])->name('teams.create-role');
        Route::get('my/teams/{team}/roles/{roleId}/edit', [TeamController::class, 'showEditRole'])->name('teams.roles.edit');
        Route::put('my/teams/{team}/roles/{roleId}', [TeamController::class, 'updateRole'])->name('teams.update-role');
        Route::delete('my/teams/{team}/roles/{roleId}', [TeamController::class, 'deleteRole'])->name('teams.delete-role');
    });
});

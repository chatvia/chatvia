<?php

use App\Http\Controllers\Settings\Account\DataExportController;
use App\Http\Controllers\Settings\Account\DeleteAccountController;
use App\Http\Controllers\Settings\Account\PasskeyController;
use App\Http\Controllers\Settings\Account\PasswordController;
use App\Http\Controllers\Settings\Account\PreferencesController;
use App\Http\Controllers\Settings\Account\ProfileController;
use App\Http\Controllers\Settings\Account\SocialController;
use App\Http\Controllers\Settings\Account\TwoFactorController;
use Illuminate\Support\Facades\Route;

// Personal account area — the signed-in user's own profile, security and data.
// Everything lives under `my/account/*` with `account.*` route names. Workspace
// administration (integrations, people, governance) lives in routes/workspace.php.
Route::middleware(['auth', 'can.access.dashboard'])->group(function (): void {
    Route::redirect('my/account', '/my/account/profile');

    Route::prefix('my/account')->name('account.')->group(function (): void {
        Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::delete('profile/photo', [ProfileController::class, 'destroyPhoto'])->name('profile.photo.destroy');

        // Account deletion lives on its own page so the destructive flow has the
        // user's full attention. Two-factor: re-enter the password (request) →
        // open the signed email link (confirm, 30-min expiry) → final explicit
        // click (destroy). Workspace owners only — gated in the controller +
        // FormRequest.
        Route::get('delete-account', [DeleteAccountController::class, 'edit'])->name('delete');
        Route::post('delete-account', [DeleteAccountController::class, 'request'])
            ->middleware('throttle:5,60')
            ->name('delete.request');
        Route::get('delete-account/confirm/{userId}', [DeleteAccountController::class, 'confirm'])
            ->whereNumber('userId')
            ->middleware('signed')
            ->name('delete.confirm');
        Route::delete('delete-account', [DeleteAccountController::class, 'destroy'])->name('destroy');

        Route::get('password', [PasswordController::class, 'edit'])->name('password.edit');
        Route::put('password', [PasswordController::class, 'update'])->name('password.update');

        Route::get('two-factor', [TwoFactorController::class, 'edit'])->name('two-factor.edit');
        Route::get('passkeys', [PasskeyController::class, 'edit'])->name('passkeys.edit');

        Route::get('social', [SocialController::class, 'edit'])->name('social.edit');
        Route::get('social/{provider}/connect', [SocialController::class, 'connect'])
            ->middleware('throttle:10,1')
            ->name('social.connect');
        Route::delete('social/{provider}', [SocialController::class, 'disconnect'])
            ->middleware('throttle:10,1')
            ->name('social.disconnect');

        // GDPR Art. 15/20 — let the user request a copy of their personal data
        // and download it for 24 hours before it's pruned by
        // `app:prune-expired-data-exports`.
        Route::get('data-export', [DataExportController::class, 'edit'])->name('data-export.edit');
        Route::post('data-export', [DataExportController::class, 'store'])
            ->middleware('throttle:5,60')
            ->name('data-export.store');
        Route::get('data-export/{export}/download', [DataExportController::class, 'download'])->name('data-export.download');
        Route::delete('data-export/{export}', [DataExportController::class, 'destroy'])->name('data-export.destroy');

        // Date & time preference — its form lives on the Profile page (merged from
        // the former standalone Preferences page), so only the write endpoint remains.
        Route::patch('preferences', [PreferencesController::class, 'update'])->name('preferences.update');
    });
});

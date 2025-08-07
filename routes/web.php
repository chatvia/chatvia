<?php

use App\Http\Controllers\Auth\Social\GoogleAuthController;
use App\Http\Controllers\Auth\Social\GoogleCallbackAuthController;
use App\Http\Controllers\ConsentController;
use App\Http\Controllers\PrivacyController;
use App\Http\Controllers\RefundController;
use App\Http\Controllers\TermsController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('landing.index');
})->name('home');

// Consent routes
Route::get('/consent', [ConsentController::class, 'show'])->name('consent.show');
Route::post('/consent', [ConsentController::class, 'store'])->name('consent.store');
Route::get('/privacy-policy', PrivacyController::class)->name('privacy');
Route::get('/terms-of-service', TermsController::class)->name('terms');
Route::get('/refund', RefundController::class)->name('refund');

Route::get('/auth/google', GoogleAuthController::class)->name('auth.google');
Route::get('/auth/google/callback', GoogleCallbackAuthController::class)->name('auth.google.callback');

require __DIR__.'/dashboard.php';
require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
require __DIR__.'/chat.php';
require __DIR__.'/agents.php';
require __DIR__.'/billing.php';
require __DIR__.'/ingestion.php';
require __DIR__.'/local.php';

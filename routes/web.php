<?php

use App\Http\Controllers\Auth\Social\GoogleAuthController;
use App\Http\Controllers\Auth\Social\GoogleCallbackAuthController;
use App\Http\Controllers\Auth\Social\MicrosoftAuthController;
use App\Http\Controllers\Auth\Social\MicrosoftCallbackAuthController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\ConsentController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Landing\ChangelogController;
use App\Http\Controllers\Landing\PrivacyController;
use App\Http\Controllers\Landing\RefundController;
use App\Http\Controllers\Landing\TermsController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\RequestDemoController;
use App\Http\Controllers\UseCasesController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

// Consent routes
Route::get('/consent', [ConsentController::class, 'show'])->name('consent.show');
Route::post('/consent', [ConsentController::class, 'store'])->name('consent.store');
Route::get('/privacy-policy', PrivacyController::class)->name('privacy');
Route::get('/terms-of-service', TermsController::class)->name('terms');
Route::get('/refund', RefundController::class)->name('refund');
Route::get('/changelog', ChangelogController::class)->name('changelog');
Route::get('/blog', BlogController::class)->name('blog');
Route::get('/post', PostController::class)->name('blog.post');

Route::get('demo', RequestDemoController::class)->name('demo');
Route::post('demo', RequestDemoController::class)->name('demo.submit');

Route::get('docs', \App\Http\Controllers\DocsController::class)->name('docs');

Route::get('/auth/google', GoogleAuthController::class)->name('auth.google');
Route::get('/auth/google/callback', GoogleCallbackAuthController::class)->name('auth.google.callback');

Route::get('/auth/microsoft', MicrosoftAuthController::class)->name('auth.microsoft');
Route::get('/auth/microsoft/callback', MicrosoftCallbackAuthController::class)->name('auth.microsoft.callback');

Route::get('/use-cases/{case}', UseCasesController::class)->name('use-cases');

require __DIR__.'/dashboard.php';
require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
require __DIR__.'/chat.php';
require __DIR__.'/agents.php';
require __DIR__.'/billing.php';
require __DIR__.'/ingestion.php';
require __DIR__.'/local.php';

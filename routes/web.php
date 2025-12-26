<?php

use App\Http\Controllers\AgentChunkImageViewController;
use App\Http\Controllers\Auth\Social\GoogleAuthController;
use App\Http\Controllers\Auth\Social\GoogleCallbackAuthController;
use App\Http\Controllers\Auth\Social\MicrosoftAuthController;
use App\Http\Controllers\Auth\Social\MicrosoftCallbackAuthController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\ChatPdfController;
use App\Http\Controllers\ConsentController;
use App\Http\Controllers\DocsController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Landing\ChangelogController;
use App\Http\Controllers\Landing\PrivacyController;
use App\Http\Controllers\Landing\RefundController;
use App\Http\Controllers\Landing\TermsController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\PricingController;
use App\Http\Controllers\RequestDemoController;
use App\Http\Controllers\SlackAuthController;
use App\Http\Controllers\SlackCallbackController;
use App\Http\Controllers\TrialCheckoutController;
use App\Http\Controllers\TrialSuccessController;
use App\Http\Controllers\UseCasesController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::get('/pricing', PricingController::class)->name('pricing');

// Consent routes
Route::get('/consent', [ConsentController::class, 'show'])->name('consent.show');
Route::post('/consent', [ConsentController::class, 'store'])->name('consent.store');
Route::get('/privacy-policy', PrivacyController::class)->name('privacy');
Route::get('/terms-of-service', TermsController::class)->name('terms');
Route::get('/refund', RefundController::class)->name('refund');
Route::get('/changelog', ChangelogController::class)->name('changelog');
Route::get('/blog', BlogController::class)->name('blog');
Route::get('/blog/{post:slug}', PostController::class)->name('blog.post');

Route::get('demo', RequestDemoController::class)->name('demo');
Route::post('demo', RequestDemoController::class)->name('demo.submit');

Route::get('docs', DocsController::class)->name('docs');

Route::get('/auth/google', GoogleAuthController::class)->name('auth.google');
Route::get('/auth/google/callback', GoogleCallbackAuthController::class)->name('auth.google.callback');

Route::get('/auth/microsoft', MicrosoftAuthController::class)->name('auth.microsoft');
Route::get('/auth/microsoft/callback', MicrosoftCallbackAuthController::class)->name('auth.microsoft.callback');

Route::get('/auth/slack', SlackAuthController::class)->name('auth.slack');
Route::get('/auth/slack/callback', SlackCallbackController::class)->name('auth.slack.callback');

Route::get('/use-cases/{case}', UseCasesController::class)->name('use-cases');

Route::get('free-tools/chat-with-pdf', ChatPdfController::class)->name('free-tools.chat-with-pdf');

// Trial routes (guest accessible)
Route::post('trial/checkout', TrialCheckoutController::class)->name('trial.checkout');
Route::get('trial/success', TrialSuccessController::class)->name('trial.success');

Route::get('my/chunks/{chunkExternalId}/{imageIndex}.png', AgentChunkImageViewController::class)->name('chunks.image.view');

require __DIR__.'/dashboard.php';
require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
require __DIR__.'/chat.php';
require __DIR__.'/agents.php';
require __DIR__.'/billing.php';
require __DIR__.'/ingestion.php';
require __DIR__.'/local.php';

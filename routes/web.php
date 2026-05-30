<?php

use App\Http\Controllers\Agents\Chunks\AgentChunkImageViewController;
use App\Http\Controllers\Auth\Social\GoogleAuthController;
use App\Http\Controllers\Auth\Social\GoogleCallbackAuthController;
use App\Http\Controllers\Auth\Social\MicrosoftAuthController;
use App\Http\Controllers\Auth\Social\MicrosoftCallbackAuthController;
use App\Http\Controllers\Auth\Social\Slack\SlackAuthController;
use App\Http\Controllers\Auth\Social\Slack\SlackCallbackController;
use App\Http\Controllers\Billing\Trials\TrialCheckoutController;
use App\Http\Controllers\Billing\Trials\TrialSuccessController;
use App\Http\Controllers\Connectors\ConnectorOAuthCallbackController;
use App\Http\Controllers\KnowledgeFiles\SourceFileViewController;
use App\Http\Controllers\Landing\Blog\BlogController;
use App\Http\Controllers\Landing\Blog\ChangelogController;
use App\Http\Controllers\Landing\Blog\PostController;
use App\Http\Controllers\Landing\FreeTools\ChatPdfController;
use App\Http\Controllers\Landing\HomeController;
use App\Http\Controllers\Landing\Legal\ConsentController;
use App\Http\Controllers\Landing\Legal\PrivacyController;
use App\Http\Controllers\Landing\Legal\RefundController;
use App\Http\Controllers\Landing\Legal\TermsController;
use App\Http\Controllers\Landing\Marketing\DocsController;
use App\Http\Controllers\Landing\Marketing\PricingController;
use App\Http\Controllers\Landing\Marketing\RequestDemoController;
use App\Http\Controllers\Landing\Marketing\UseCasesController;
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

Route::get('external/files/{type}/{externalId}', SourceFileViewController::class)->name('external.source.files.view');

// Global OAuth callback for MCP connectors. The `state` query param carries
// the agent + connector slug, so this is a single endpoint shared across
// every preset.
Route::get(
    'connectors/oauth/callback',
    ConnectorOAuthCallbackController::class,
)->middleware(['web', 'auth'])->name('connectors.oauth.callback');


require __DIR__.'/dashboard.php';
require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
require __DIR__.'/chat.php';
require __DIR__.'/agents.php';
require __DIR__.'/billing.php';
require __DIR__.'/ingestion.php';
require __DIR__.'/local.php';

<?php

use App\Http\Controllers\Agent\Channel\AgentInstagramChannelController;
use App\Http\Controllers\Agent\Channel\AgentMessengerChannelController;
use App\Http\Controllers\Agent\Chunks\AgentChunkImageViewController;
use App\Http\Controllers\Auth\Social\GithubAuthController;
use App\Http\Controllers\Auth\Social\GithubCallbackAuthController;
use App\Http\Controllers\Auth\Social\GoogleAuthController;
use App\Http\Controllers\Auth\Social\GoogleCallbackAuthController;
use App\Http\Controllers\Auth\Social\MicrosoftAuthController;
use App\Http\Controllers\Auth\Social\MicrosoftCallbackAuthController;
use App\Http\Controllers\Auth\Social\Slack\SlackAuthController;
use App\Http\Controllers\Auth\Social\Slack\SlackCallbackController;
use App\Http\Controllers\Billing\Trials\TrialCheckoutController;
use App\Http\Controllers\Billing\Trials\TrialSuccessController;
use App\Http\Controllers\Connector\ConnectorOAuthCallbackController;
use App\Http\Controllers\KnowledgeFiles\SourceFileViewController;
use App\Http\Controllers\Landing\AppHomeController;
use App\Http\Controllers\Landing\Blog\BlogController;
use App\Http\Controllers\Landing\Blog\ChangelogController;
use App\Http\Controllers\Landing\Blog\PostController;
use App\Http\Controllers\Landing\FreeTools\ChatPdfController;
use App\Http\Controllers\Landing\HomeController;
use App\Http\Controllers\Landing\Legal\DpaController;
use App\Http\Controllers\Landing\Legal\PrivacyController;
use App\Http\Controllers\Landing\Legal\RefundController;
use App\Http\Controllers\Landing\Legal\SecurityController;
use App\Http\Controllers\Landing\Legal\SubprocessorsController;
use App\Http\Controllers\Landing\Legal\TermsController;
use App\Http\Controllers\Landing\Marketing\ContactController;
use App\Http\Controllers\Landing\Marketing\MissionController;
use App\Http\Controllers\Landing\Marketing\PricingController;
use App\Http\Controllers\Landing\Marketing\UseCaseController;
use App\Http\Middleware\Access\EnsureBillingIsEnabled;
use App\Http\Middleware\Web\ResolveLandingLocale;
use Illuminate\Support\Facades\Route;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;

// The public marketing site (landing, pricing, mission, security, legal, blog,
// contact) is optional: a self-hosted install turns it off with
// MARKETING_SITE_ENABLED=false. Its routes are then not registered at all, and
// `/` (still named `home`, which the app redirects to) sends visitors to the app.
$marketingSite = (bool) config('chatvia.brand.marketing_site');

if (! $marketingSite) {
    Route::group([
        'prefix' => LaravelLocalization::setLocale(),
    ], function (): void {
        Route::get('/', AppHomeController::class)->name('home');
    });
}

// Public marketing + legal pages, served under an explicit locale segment
// (e.g. `/en`, `/ar`). `localeSessionRedirect` forwards a visitor to a remembered/
// preferred locale; `localizationRedirect` redirects an un-prefixed URL like `/`
// to `/en`. OAuth callbacks, every POST endpoint, and the trial/Stripe flow stay
// OUTSIDE this group — their URLs must not be locale-prefixed or redirected.
Route::group([
    'prefix' => LaravelLocalization::setLocale(),
    'middleware' => [ResolveLandingLocale::class, 'localeSessionRedirect', 'localizationRedirect'],
], function () use ($marketingSite): void {
    if (! $marketingSite) {
        return;
    }

    Route::get('/', HomeController::class)->name('home');
    Route::get('/pricing', PricingController::class)->name('pricing');
    Route::get('/mission', MissionController::class)->name('mission');
    Route::get('/security', SecurityController::class)->name('security');

    Route::get('/privacy-policy', PrivacyController::class)->name('privacy');
    Route::get('/terms-of-service', TermsController::class)->name('terms');
    Route::get('/refund', RefundController::class)->name('refund');
    Route::get('/legal/dpa', [DpaController::class, 'show'])->name('dpa');
    Route::get('/legal/subprocessors', SubprocessorsController::class)->name('subprocessors');
    Route::get('/changelog', ChangelogController::class)->name('changelog');
    Route::get('/blog', BlogController::class)->name('blog');
    Route::get('/blog/{post:slug}', PostController::class)->name('blog.post');

    Route::get('contact', [ContactController::class, 'show'])->name('contact');
    Route::get('/use-cases/{case}', UseCaseController::class)->name('use-cases');
    Route::get('free-tools/chat-with-pdf', ChatPdfController::class)->name('free-tools.chat-with-pdf');
});

// Authenticated dashboard, served under the same `/{locale}` prefix so every app
// URL is locale-explicit. Only `localeSessionRedirect` (forwards an un-prefixed
// `/dashboard` to `/en/dashboard`) — NOT `ResolveLandingLocale`, which would
// persist the URL locale onto the account on every request. The rendered language
// still follows the signed-in user's saved `users.locale`. Each required file
// keeps its own auth/permission middleware.
Route::group([
    'prefix' => LaravelLocalization::setLocale(),
    'middleware' => ['localeSessionRedirect'],
], function (): void {
    require __DIR__.'/dashboard.php';
    require __DIR__.'/account.php';
    require __DIR__.'/workspace.php';
    require __DIR__.'/agents.php';
    require __DIR__.'/billing.php';
});

if ($marketingSite) {
    // Each download starts a headless browser, so cap it per visitor. The named
    // prefix keeps this counter separate from other plain `throttle:` routes.
    Route::get('/legal/dpa/download', [DpaController::class, 'download'])
        ->middleware('throttle:10,1,dpa-download')
        ->name('dpa.download');

    // Contact form submission posts to the non-localized action URL (a localized POST
    // would be redirected to a GET by the locale middleware).
    Route::post('contact', [ContactController::class, 'submit'])
        ->middleware('throttle:5,1,contact')
        ->name('contact.submit');
}

// Social OAuth routes. Starts get a tighter cap (cheap redirect, only abused
// for traffic generation); callbacks get a higher cap (legit users can land
// here twice in quick succession during link-then-bounce flows, and we don't
// want a flaky network retry to lock them out).
Route::get('/auth/google', GoogleAuthController::class)->middleware('throttle:10,1')->name('auth.google');
Route::get('/auth/google/callback', GoogleCallbackAuthController::class)->middleware('throttle:30,1')->name('auth.google.callback');

Route::get('/auth/github', GithubAuthController::class)->middleware('throttle:10,1')->name('auth.github');
Route::get('/auth/github/callback', GithubCallbackAuthController::class)->middleware('throttle:30,1')->name('auth.github.callback');

Route::get('/auth/microsoft', MicrosoftAuthController::class)->middleware('throttle:10,1')->name('auth.microsoft');
Route::get('/auth/microsoft/callback', MicrosoftCallbackAuthController::class)->middleware('throttle:30,1')->name('auth.microsoft.callback');

Route::get('/auth/slack', SlackAuthController::class)->middleware('throttle:10,1')->name('auth.slack');
Route::get('/auth/slack/callback', SlackCallbackController::class)->middleware('throttle:30,1')->name('auth.slack.callback');

// Trial routes (guest accessible). They answer 404 when billing is turned off.
// Every checkout call creates a Stripe Checkout session, so cap it per visitor.
Route::post('trial/checkout', TrialCheckoutController::class)
    ->middleware([EnsureBillingIsEnabled::class, 'throttle:10,1,trial-checkout'])
    ->name('trial.checkout');
Route::get('trial/success', TrialSuccessController::class)
    ->middleware(EnsureBillingIsEnabled::class)
    ->name('trial.success');

Route::get('my/chunks/{chunkExternalId}/{imageIndex}.png', AgentChunkImageViewController::class)->name('chunks.image.view');

// Serves a tenant's uploaded knowledge-base file. Requires auth; the controller
// additionally scopes the file to the caller's workspace (cross-tenant IDOR).
Route::get('external/files/{type}/{externalId}', SourceFileViewController::class)
    ->middleware(['web', 'auth'])
    ->name('external.source.files.view');

// Global OAuth callback for MCP connectors. The `state` query param carries
// the agent + connector slug, so this is a single endpoint shared across
// every preset.
Route::get(
    'connectors/oauth/callback',
    ConnectorOAuthCallbackController::class,
)->middleware(['web', 'auth'])->name('connectors.oauth.callback');

// Meta (Messenger/Instagram) channel OAuth callbacks. The Meta app whitelists a
// single fixed redirect URI, so these MUST stay un-prefixed — the agent rides in
// the signed OAuth `state`, not the URL. Mirrors the middleware of the `my/` group
// in agents.php (which is itself now locale-prefixed inside the group above).
Route::middleware(['auth', 'verified', 'user.subscribed', 'can.access.dashboard'])
    ->prefix('my')
    ->group(function (): void {
        Route::get('channels/messenger/oauth/callback', [AgentMessengerChannelController::class, 'callback'])->name('channels.messenger.callback');
        Route::get('channels/instagram/oauth/callback', [AgentInstagramChannelController::class, 'callback'])->name('channels.instagram.callback');
    });

// Un-prefixed surfaces: the end-user chat (its own widget i18n + iframe embedding),
// transactional/email + invitation links (auth.php), and webhook/dev endpoints.
require __DIR__.'/auth.php';
require __DIR__.'/chat.php';
require __DIR__.'/ingestion.php';
require __DIR__.'/local.php';

<?php

declare(strict_types=1);

use App\Admin\Settings\ModelLogoController;
use App\Http\Controllers\Agent\Chunks\AgentChunkImageViewController;
use App\Http\Controllers\Auth\Social\GithubAuthController;
use App\Http\Controllers\Auth\Social\GithubCallbackAuthController;
use App\Http\Controllers\Auth\Social\GoogleAuthController;
use App\Http\Controllers\Auth\Social\GoogleCallbackAuthController;
use App\Http\Controllers\Auth\Social\MicrosoftAuthController;
use App\Http\Controllers\Auth\Social\MicrosoftCallbackAuthController;
use App\Http\Controllers\Auth\Social\Slack\SlackAuthController;
use App\Http\Controllers\Auth\Social\Slack\SlackCallbackController;
use App\Http\Controllers\Chat\Messages\AttachmentViewController;
use App\Http\Controllers\Chat\Messages\GeneratedMediaViewController;
use App\Http\Controllers\Connector\ConnectorOAuthCallbackController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\KnowledgeFiles\SourceFileViewController;
use App\Http\Middleware\Web\ContentSecurityPolicy;
use Domain\Llm\Services\ModelsDevCatalog;
use Illuminate\Support\Facades\Route;

// `/` has no landing page: guests go to the login form, signed-in users to
// their dashboard.
Route::get('/', HomeController::class)->name('home');

// Authenticated dashboard. URLs carry no language segment: the rendered
// language follows the signed-in user's saved `users.locale` (SetLocale). Each
// required file keeps its own auth/permission middleware.
require __DIR__.'/dashboard.php';
require __DIR__.'/account.php';
require __DIR__.'/workspace.php';
require __DIR__.'/agents.php';

// Social OAuth routes. Starts get a tighter cap (cheap redirect, only abused
// for traffic generation); callbacks get a higher cap (legit users can land
// here twice in quick succession during link-then-bounce flows, and we don't
// want a flaky network retry to lock them out).
Route::get('/auth/google', GoogleAuthController::class)->middleware('throttle:10,1,social-start')->name('auth.google');
Route::get('/auth/google/callback', GoogleCallbackAuthController::class)->middleware('throttle:30,1,social-callback')->name('auth.google.callback');

Route::get('/auth/github', GithubAuthController::class)->middleware('throttle:10,1,social-start')->name('auth.github');
Route::get('/auth/github/callback', GithubCallbackAuthController::class)->middleware('throttle:30,1,social-callback')->name('auth.github.callback');

Route::get('/auth/microsoft', MicrosoftAuthController::class)->middleware('throttle:10,1,social-start')->name('auth.microsoft');
Route::get('/auth/microsoft/callback', MicrosoftCallbackAuthController::class)->middleware('throttle:30,1,social-callback')->name('auth.microsoft.callback');

Route::get('/auth/slack', SlackAuthController::class)->middleware('throttle:10,1,social-start')->name('auth.slack');
Route::get('/auth/slack/callback', SlackCallbackController::class)->middleware('throttle:30,1,social-callback')->name('auth.slack.callback');

Route::get('my/chunks/{chunkExternalId}/{imageIndex}.png', AgentChunkImageViewController::class)->name('chunks.image.view');

// A file attached to a conversation. Public like the chunk images, so only
// the signed link the attachment makes is accepted. The signature leaves out
// the host, so the link works wherever the chat is shown, and covers the
// file's extension, which is there so the link says what it opens.
Route::get('media/attachments/{attachment}.{extension}', AttachmentViewController::class)
    ->middleware('signed:relative')
    ->name('attachments.view');

// An image, audio file or video an agent made in a chat, on the same terms.
Route::get('media/{generatedMedia}.{extension}', GeneratedMediaViewController::class)
    ->middleware('signed:relative')
    ->name('generated-media.view');

// A model maker's logo (models.dev), for the model pickers chatters see in
// the hosted chat and the widget. Public: logos hold nothing of a workspace.
Route::get('model-logos/{logo}.svg', ModelLogoController::class)
    ->where('logo', ModelsDevCatalog::LOGO_PATTERN)
    ->middleware('throttle:120,1,model-logos')
    ->name('model-logo');

// Serves a tenant's uploaded knowledge-base file. Requires a dashboard
// account; the controller scopes the file to the caller's workspace and agents.
// It sends its own sandboxing Content-Security-Policy, which the app-wide one
// would replace.
Route::get('external/files/{type}/{externalId}', SourceFileViewController::class)
    ->middleware(['web', 'auth', 'can.access.dashboard'])
    ->withoutMiddleware(ContentSecurityPolicy::class)
    ->name('external.source.files.view');

// Global OAuth callback for MCP connectors. The `state` query param carries
// the agent + connector slug, so this is a single endpoint shared across
// every preset.
Route::get(
    'connectors/oauth/callback',
    ConnectorOAuthCallbackController::class,
)->middleware(['web', 'auth', 'can.access.dashboard', 'throttle:30,1,connector-oauth-callback'])->name('connectors.oauth.callback');

// The end-user chat (its own widget i18n + iframe embedding), transactional/email
// + invitation links (auth.php), and dev endpoints.
require __DIR__.'/auth.php';
require __DIR__.'/chat.php';
require __DIR__.'/local.php';
